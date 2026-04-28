<?php

require_once "llmWrapper.php";
require_once "functions.php";
require_once "dynamicFunctions.php";

class LLMSurvey extends PluginBase {
    protected $storage = "DbStorage";
    static protected $description = "This plugin allows personalized text to be dynamically generated from a language model (LLM) based on participant input and to be rated directly afterwards within the survey.";
    static protected $name = "LLMSurvey";

    public function init() {
        // subscribe to events
        // this function defines the general structure of the plugin

        // fired before the advanced settings page is displayed
        // used to add custom settings to the survey settings page
        $this->subscribe("beforeSurveySettings");

        // fired when the survey settings are saved
        // used to save custom settings from the survey settings page
        $this->subscribe("newSurveySettings", "saveSurveySettings");

        // fired when a question is created
        // used to add a custom attribute to questions to mark them as LLM questions
        $this->subscribe("newQuestionAttributes");

        // fired just before the survey is activated
        // used to check if settings are correct and to automatically add hidden questions to save the LLM-outputs
        $this->subscribe("beforeSurveyActivate");

        // fired before each survey page loads
        // used to attach the subquestion handler and the loading screen script and to save the dynamically generated data
        $this->subscribe("beforeSurveyPage");

        // fired before the question is rendered
        // used to generate LLM-output and replace the placeholders in the question text with the generated content
        $this->subscribe("beforeQuestionRender");

        // fired after a user response is deleted
        // used to delete corresponding response entrys from plugin db
        $this->subscribe("afterResponseDelete");

        // fired after a user completes the survey
        // used to save the generated LLM-outputs in the corresponding hidden questions and to delete response-dependent entries from the plugin table
        $this->subscribe("afterSurveyComplete");

        // fired after a survey was deactivated
        // used to delete responses from plugin settings for this survey
        $this->subscribe("afterSurveyDeactivate");

        // fired before a survey is deleted
        // used to delete all plugin settings and responses for this survey
        $this->subscribe("beforeSurveyDelete");

        // fired before this plugin is deactivated
        // used to delete all stored survey data for this plugin
        $this->subscribe("beforeDeactivate");
    }

    /**
     * Sets the survey settings for the plugin in the survey settings page.
     */
    public function beforeSurveySettings() {
        $oEvent = $this->event;
        $oEvent->set("surveysettings.{$this->id}", array(
            "name" => get_class($this),
            "settings" => array(
                "helpText" => array(
                    "type" => "info",
                    "label" => "Info",
                    "help" => "These are the plugin settings for this survey. Please activate the survey AFTER you've activated the plugin in the setting below. The survey can be deactivated and activated again. You can find the <a href='https://github.com/HellPing219/LLM-Survey' target='_blank'>full documentation here</a>."
                ),
                "bUse" => array(
                    "type" => "boolean",
                    "label" => "Activate Plugin",
                    "help" => "Mit der Nutzung des Umfragetools bestätige ich, dass ich keine personenbezogenen Daten (vgl. DSGVO Art. 4 Nr. 1) unbefugt oder ohne Aufklärung der Umfrageteilnehmer an Modellbetreiber weitergebe.",
                    "current" => $this->get("bUse", "Survey", $oEvent->get("survey"), "0")
                ),
                "openRouterKey" => array(
                    "type" => "password",
                    "label" => "Open Router API Key",
                    "current" => $this->get("openRouterKey", "Survey", $oEvent->get("survey"))
                ),
                "debugMode" => array(
                    "type" => "boolean",
                    "label" => "Debug Mode",
                    "help" => "Enable to log prompts and generated output to the browsers console when conducting the survey.",
                    "current" => $this->get("debugMode", "Survey", $oEvent->get("survey"), "0")
                ),
                "systemPromptInput" => array(
                    "type" => "text",
                    "label" => "System Prompt",
                    "help" => "Insert the system prompt. Changing this setting can break this survey - please proceed with caution. The plugin needs an associative array of strings back from the LLM in order to map the outputs to the correct placeholders.",
                    "current" => $this->get("systemPromptInput", "Survey", $oEvent->get("survey"), llmWrapper::$defaultSystemPrompt)
                ),
                "customModelKey1" => array(
                    "type" => "password",
                    "label" => "Custom Model API Key 1",
                    "current" => $this->get("customModelKey1", "Survey", $oEvent->get("survey"))
                ),
                "customModelKey2" => array(
                    "type" => "password",
                    "label" => "Custom Model API Key 2",
                    "current" => $this->get("customModelKey2", "Survey", $oEvent->get("survey"))
                ),
                "customModelKey3" => array(
                    "type" => "password",
                    "label" => "Custom Model API Key 3",
                    "current" => $this->get("customModelKey3", "Survey", $oEvent->get("survey"))
                )
            )
        ));
    }

    /**
     * Saves the survey settings for the current survey.
     */
    public function saveSurveySettings() {
        $event = $this->event;
        foreach ($event->get("settings") as $name => $value) {
            $this->set($name, $value, "Survey", $event->get("survey"));
        }
    }

    /**
     * Appends a custom attribute to a question where a user can select if the question should be an LLM question.
     */
    public function newQuestionAttributes() {
        // add help script to question attribute so it loads when editing a question
        $jsFilePath = "../../plugins/LLMSurvey/questionHelp.js";
        $scriptTag = "<script src='{$jsFilePath}'></script>";

        $event = $this->getEvent();
        $questionAttributes = array(
            "llmElement" => array(
                "types" => "15A*BC:DE!FGHIKLMNOPQRS;TU|XY", // apply to all question types
                "category" => "LLMSurvey",
                "sortorder" => 1,
                "inputtype" => "switch",
                "default" => 0,
                "help" => "Select if this question should define a prompt and replace its contents upon loading in an active survey.$scriptTag",
                "caption" => "LLM Output Question"
            )
        );
        $event->append("questionAttributes", $questionAttributes);
    }

    /**
     * Checks if settings are correct and automatically adds hidden questions to save the LLM-outputs.
     * If checks fail, the survey activation is aborted.
     */
    public function beforeSurveyActivate() {
        $event = $this->event;
        $surveyId = $event->get("surveyId");

        $active = $this->get("bUse", "Survey", $surveyId, false);
        if (!$active) {
            // Plugin is not active for this survey. Skipping checks
            return;
        }

        $survey = Survey::model()->findByPk($surveyId);
        if (!$survey) {
            $event->set("success", false);
            $event->set("message", "Internal error: Could not get the survey object.");
            return;
        }

        // get all questions (by filtering subquestions)
        $allQuestions = array_values(array_filter($survey->getAllQuestions(), function ($q) {
            return !isset($q->parent);
        }));

        // check if everything is correctly set up
        $checksOK = functions::performChecks(
            $event,
            $this->get("openRouterKey", "Survey", $surveyId),
            $survey,
            $allQuestions
        );
        if (!$checksOK) {
            // just return - the error message was already set in performChecks
            return;
        }

        // sort questions by question_order
        usort($allQuestions, function ($item1, $item2) {
            $group1 = QuestionGroup::model()->findByPk($item1->gid);
            $group2 = QuestionGroup::model()->findByPk($item2->gid);
            if ($group1->gid != $group2->gid) {
                return $group1->group_order > $group2->group_order;
            }
            return $item1->question_order > $item2->question_order;
        });

        // we want to insert a hidden question for each prompt/customApi field of the LLM questions right before the LLM question
        // to preserve the order we need to update the order of the later questions when inserting a new question
        $currentGid = $allQuestions[0]->gid;
        $currentOrder = 0;
        foreach ($allQuestions as $q) {
            if ($currentGid != $q->gid) {
                // we are in a new question group, reset order
                $currentGid = $q->gid;
                $currentOrder = 1;
            } else {
                // still same question group, increase order
                $currentOrder++;
            }

            if (!functions::isPlaceholderQuestion($q)) {
                // current question is a normal question, update question order and continue
                functions::updateQuestionOrder($q, $currentOrder);
                continue;
            }

            // the current question is an LLM question
            $questionData = functions::getPlaceholderQuestionArray($survey->language, $q);
            // count number of prompt fields in question data
            $numPrompts = 0;
            foreach ($questionData as $item) {
                if ($item["type"] == "prompt" || $item["type"] == "customApi") {
                    $numPrompts++;
                }
            }

            // insert a hidden question for each prompt object for later storage of the output
            for ($j = 0; $j < $numPrompts; $j++) {
                $successOut = functions::insertQuestion(
                    $surveyId,
                    $currentGid,
                    "T",
                    $currentOrder,
                    $q->title . "output$j",
                    "LLM Output $j (" . $q->title . ")",
                    $survey->language
                );
                if ($successOut) {
                    // only increase order if question was successfully inserted
                    $currentOrder++;
                }
            }
            // finally update order of the current LLM question
            functions::updateQuestionOrder($q, $currentOrder);
        }
    }

    /**
     * Attaches the subquestion handler and the loading screen script and saves the dynamically generated data.
     * LimeSurvey saves question data after each page load, which overwrites the manually saved data.
     * Therefore, we need to save the data again before each page load.
     */
    public function beforeSurveyPage() {
        $event = $this->event;
        $surveyId = $event->get("surveyId");

        // check if plugin is active
        $active = $this->get("bUse", "Survey", $surveyId, false);
        if (!$active) {
            return;
        }

        // attach subquestion handler script
        self::attachScriptToPage("subQuestionLogic.js");
        // attach loading screen script
        self::attachScriptToPage("loadingScreen.js");

        // get response id through session
        $responseId = Yii::app()->session["survey_$surveyId"]["srid"];
        if (!isset($responseId)) {
            return;
        }

        self::saveCurrentSurveyState($surveyId, $responseId);
    }

    /**
     * Appends a custom script to the current page.
     * @param string $scriptUrl The path of the script to attach.
     */
    function attachScriptToPage(string $scriptUrl) {
        $script = $this->publish($scriptUrl);
        App()->clientScript->registerScriptFile($script, LSYii_ClientScript::POS_END);
    }

    /**
     * Inserts the generated LLM-outputs into the corresponding question fields in the database.
     * @param int $surveyId The ID of the survey.
     * @param int $responseId The ID of the user response.
     */
    function saveCurrentSurveyState(int $surveyId, int $responseId) {
        $survey = Survey::model()->findByPk($surveyId);
        $allQuestions = $survey->getAllQuestions();

        // iterate over every question and find save questions
        foreach ($allQuestions as $q) {
            if (functions::isSaveQuestion($q)) {
                // get saved output in plugin settings and write data into the field of the save question
                $llmOutput = $this->get($q->title, "Response_$surveyId", $responseId);
                $strOutput = json_encode($llmOutput);
                dynamicFunctions::insertAnswerIntoDB($this, $surveyId, $responseId, $q->qid, $strOutput);
            }
        }
    }

    /**
     * If the question is marked as an LLM question, the prompts are filled in and then sent to the endpoint.
     * The response is parsed and stored.
     * Placeholders in the question's text are replaced by the corresponding fields from the response.
     */
    public function beforeQuestionRender() {
        $event = $this->event;
        $surveyId = $event->get("surveyId");
        $qid = $event->get("qid");

        // check if plugin is active
        $active = $this->get("bUse", "Survey", $surveyId, false);
        if (!$active) {
            return;
        }

        $survey = Survey::model()->findByPk($surveyId);
        if (!$survey) {
            return;
        }
        $question = Question::model()->findByPk($qid);
        if (!$question) {
            return;
        }
        if (!functions::isPlaceholderQuestion($question)) {
            return;
        }

        // get response id through session
        $responseId = Yii::app()->session["survey_$surveyId"]["srid"];
        // response contains all answers given by the user so far as well as meta information
        $response = $this->pluginManager->getAPI()->getResponse($surveyId, $responseId);

        // get current survey language
        $surveyLanguage = $response["startlanguage"];
        if (empty($surveyLanguage)) {
            dynamicFunctions::replaceQuestionText(
                $event,
                array(),
                array("error" => "ERROR: Could not retrieve survey language."),
                "ERROR: Could not retrieve survey language.\n" . print_r($response, true) . "\n\n"
            );
            return;
        }

        // get question data
        $questionData = functions::getPlaceholderQuestionArray($surveyLanguage, $question);

        // get all generated LLM outputs for this user response so far
        $allOutputs = (array) $this->get("allOutputs", "Response_$surveyId", $responseId, array());

        // initially add defaultSystemprompt to allOutputs so it can be used as a placeholder for customApi
        if (empty($allOutputs)) {
            $allOutputs = array("SYSTEMPROMPT" => $this->get("systemPromptInput", "Survey", $surveyId, llmWrapper::$defaultSystemPrompt));
        }

        // perform a request for each prompt/customApi field in the question data
        $logMessage = "========== QUESTION [" . $question->title . "] (" . $question->qid . ") ==========\n\n";
        if (empty($questionData)) {
            $logMessage .= "ERROR: Could not parse the questions text. Please make sure it is a valid json before activating the survey.\n\n";
        }
        $llmOutputs = [];
        $promptIndex = 0;
        foreach ($questionData as $item) {
            // if type is not defined or something other than "prompt" or "customApi" -> continue
            if (empty($item["type"]) || !($item["type"] == "prompt" || $item["type"] == "customApi")) {
                continue;
            }

            // check if response for this prompt was already created
            $resp = $this->get($question->title . "output" . $promptIndex, "Response_$surveyId", $responseId);
            if (!empty($resp)) {
                // this output was already generated, we don't need to perform the request again. Skipping...
                // todo if an error was generated before, we should try again?
                $logMessage .= "PROMPT " . $promptIndex + 1 . ": Output for this transaction was already generated - using saved output from database.\n\n";
                $llmOutputs = array_merge($llmOutputs, (array) $resp);
                $promptIndex++;
                continue;
            }

            $llmOutput = array();

            // generate output depending on type
            if ($item["type"] == "prompt") {
                // create prompt with response fields from survey AND previous responses from LLMs
                $prompt = llmWrapper::createPrompt($surveyId, $item["data"], array_merge($response, $allOutputs), $surveyLanguage);
                $systemPrompt = "";
                // check if system prompt should be used
                if (empty($item["overrideSystemprompt"]) || $item["overrideSystemprompt"] != true) {
                    $systemPrompt = llmWrapper::createPrompt(
                        $surveyId,
                        $this->get("systemPromptInput", "Survey", $surveyId, llmWrapper::$defaultSystemPrompt),
                        array_merge($response, $allOutputs),
                        $surveyLanguage
                    );
                }

                // write log message for debug to print in console
                $logMessage .= "PROMPT " . $promptIndex + 1 . ": $prompt\n\n";
                $logMessage .= "SYSTEMPROMPT " . $promptIndex + 1 . ": $systemPrompt\n\n";

                // get LLM params from question text item
                $model = $item["model"];
                $temperature = $item["temperature"];
                $maxTokens = $item["maxTokens"];
                $topP = $item["topP"];
                $openRouterKey = $this->get("openRouterKey", "Survey", $surveyId);

                // get LLM output
                $llmOutput = llmWrapper::getLLMOutput($openRouterKey, $model, $systemPrompt, $prompt, $temperature, $maxTokens, $topP);
            } else if ($item["type"] == "customApi") {
                // create request headers with custom keys from survey settings as a placeholder
                $customKeys = array(
                    "CUSTOM_KEY_1" => $this->get("customModelKey1", "Survey", $surveyId),
                    "CUSTOM_KEY_2" => $this->get("customModelKey2", "Survey", $surveyId),
                    "CUSTOM_KEY_3" => $this->get("customModelKey3", "Survey", $surveyId)
                );
                $requestHeaders = llmWrapper::createBody(
                    $surveyId,
                    $item["curlHeaders"],
                    $customKeys, // only add custom keys here for the header
                    $surveyLanguage
                );
                // create request body with response fields from survey AND previous responses from LLMs
                $requestBody = llmWrapper::createBody($surveyId, $item["curlBody"], array_merge($response, $allOutputs), $surveyLanguage);

                $logMessage .= "REQUEST BODY " . $promptIndex + 1 . ":\n";
                $logMessage .= print_r($requestBody, true);

                // get LLM output
                $llmOutput = llmWrapper::getCustomLLMOutput($item["curlUrl"], $requestHeaders, $requestBody, $item["curlAnswerLocation"]);
            }
            // if output contains error -> change fields name from "error" to "error_qId"
            // this way we can identify in the frontend which prompt caused the error
            if (!empty($llmOutput["error"])) {
                $llmOutput = array("error_$qid" => $llmOutput["error"]);
            }

            // save output in db for this user response
            $this->set($question->title . "output" . $promptIndex, $llmOutput, "Response_$surveyId", $responseId);

            $logMessage .= "OUTPUT " . $promptIndex + 1 . ": " . print_r($llmOutput, true) . "\n\n\n";

            // add output array to list of existing outputs for this user response
            $llmOutputs = array_merge($llmOutputs, $llmOutput);
            $promptIndex++;
        }
        // save the generated output(s) in the prepared hidden question fields in case the survey is interrupted
        self::saveCurrentSurveyState($surveyId, $responseId);

        // add generated LLM outputs to all outputs for this user response and save in plugin settings
        $allOutputs = array_merge($allOutputs, $llmOutputs);
        $this->set("allOutputs", $allOutputs, "Response_$surveyId", $responseId);

        // append generated outputs to question text if debug mode was activated
        $debugMode = $this->get("debugMode", "Survey", $surveyId);
        dynamicFunctions::replaceQuestionText($event, $questionData, array_merge($response, $allOutputs), $debugMode ? $logMessage : "");
    }

    /**
     * Delete corresponding response entrys from plugin db.
     */
    public function afterResponseDelete() {
        $event = $this->event;
        $surveyId = $event->get("surveyId");
        $model = $event->get("model");

        $pluginId = $this->id;
        $sql = "DELETE FROM lime_plugin_settings WHERE model = 'Response_$surveyId' AND model_id = $model->id AND plugin_id = $pluginId";
        Yii::app()->db->createCommand($sql)->execute();
    }

    /**
     * Save LLM-outputs in the corresponding automatically inserted, hidden questions in the database.
     * Delete response-dependent entries from plugin table.
     */
    public function afterSurveyComplete() {
        $event = $this->event;
        $surveyId = $event->get("surveyId");

        // check if plugin is active
        $active = $this->get("bUse", "Survey", $surveyId, false);
        if (!$active) {
            return;
        }

        $responseId = $event->get("responseId");
        self::saveCurrentSurveyState($surveyId, $responseId);

        // delete saved settings for this user response from database
        $pluginId = $this->id;
        $sql = "DELETE FROM lime_plugin_settings WHERE model = 'Response_$surveyId' AND model_id = $responseId AND plugin_id = $pluginId";
        Yii::app()->db->createCommand($sql)->execute();
    }

    /**
     * Delete response-dependent entries from plugin settings for this survey.
     */
    public function afterSurveyDeactivate() {
        $event = $this->event;
        $surveyId = $event->get("surveyId");
        $pluginId = $this->id;

        $sql = "DELETE FROM lime_plugin_settings WHERE model = 'Response_$surveyId' AND plugin_id = $pluginId";
        Yii::app()->db->createCommand($sql)->execute();
    }

    /**
     * Deletes all plugin settings and response-dependent entries for this survey.
     */
    public function beforeSurveyDelete() {
        $oSurvey = $this->getEvent()->get("model");
        $surveyId = $oSurvey->sid;
        $pluginId = $this->id;

        // delete all responses for this survey
        $sql = "DELETE FROM lime_plugin_settings WHERE model = 'Response_$surveyId' AND plugin_id = $pluginId";
        Yii::app()->db->createCommand($sql)->execute();
        // delete all settings for this survey
        $sql = "DELETE FROM lime_plugin_settings WHERE model = 'Survey' AND model_id = $surveyId AND plugin_id = $pluginId";
        Yii::app()->db->createCommand($sql)->execute();
    }

    /**
     * Delete all stored survey data for this plugin.
     */
    public function beforeDeactivate() {
        $pluginId = $this->id;
        $sql = "DELETE FROM lime_plugin_settings WHERE plugin_id = $pluginId";
        Yii::app()->db->createCommand($sql)->execute();
    }
}
