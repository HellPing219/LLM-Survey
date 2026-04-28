<?php

class dynamicFunctions {
    // These functions are used dynamically while a survey is running

    /**
     * Replaces the question text during a running survey.
     * @param event The event object from beforeQuestionRender event
     * @param questionData The question data array containing the prompt(s) and the question text
     * @param outputs An associative array of user responses and generated llm outputs to replace in the question text
     * @param logMessage A message to log to the console for debugging purposes
     */
    public static function replaceQuestionText($event, array $questionData, array $outputs, $logMessage = "") {
        $questionText = "";
        // get question text from question data
        foreach ($questionData as $item) {
            if (empty($item["type"]) || $item["type"] != "text") {
                // if type is not text, skip it
                continue;
            }
            $questionText = (string) $item["data"];
            break;
        }

        // look for placeholders in text and replace them if found in outputs array
        foreach ($outputs as $key => $value) {
            $placeholder = "[$key]";
            $questionText = str_replace($placeholder, $value, $questionText);
        }

        $qid = $event->get("qid");
        // if field error in outputs for this question, append html to display the error
        if (!empty($outputs["error_$qid"])) {
            $errorTag = '<p><span style="color:red">' . $outputs["error_$qid"] . '</span></p>';
            $questionText = $questionText . $errorTag;
        }

        // create a hidden data marker element with the outputs as a json string
        // this element is used by the subquestion logic script to identify the generated data
        $dataMarker = '<span class="llmSurveyDataMarker" data-llmoutputs="' . htmlspecialchars(json_encode($outputs), ENT_QUOTES, "UTF-8") . '" style="display:none;"></span>';
        // create script tag for the log message
        $consoleMessage = json_encode($logMessage);
        $consoleScript = "<script>console.log($consoleMessage);</script>";

        // set processed text with appended tags
        $event->set("text", $questionText . $dataMarker . $consoleScript);
    }

    /**
     * Returns the table column name for a given question ID.
     * The table is created when the survey is activated.
     * This is used to insert the llm output in the answer fields in the response table when the survey is complete.
     */
    static function getTableColumnById($surveyId, $qid) {
        $availableColumns = SurveyDynamic::model($surveyId)->getAttributes();

        // find key where the ID is correct
        foreach ($availableColumns as $key => $_value) {
            // get the number after the second 'X' and compare it to our id
            if (preg_match('/X(\d+)$/', $key, $matches) && $matches[1] == $qid) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Inserts an answer to a question into the database.
     */
    public static function insertAnswerIntoDB($plugin, $surveyId, $responseId, $questionId, $answer) {
        $column = self::getTableColumnById($surveyId, $questionId);
        if (!isset($column)) {
            $plugin->log("Could not insert answer into db - this questions db field was not found. ($surveyId, $questionId)");
            return;
        }

        Yii::app()->db->createCommand()->update("lime_survey_$surveyId", array($column => $answer), "id=:id", array(":id" => $responseId));
    }
}
