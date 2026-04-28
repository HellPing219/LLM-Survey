<?php

class functions {
    // These are general functions and functions used to alter the survey or questions when the survey is not active.

    /**
     * Performs checks if the settings are in the correct values for the plugin to work.
     * Checks the placeholder questions texts and checks if the expected values are in the correct fields.
     * @param mixed $event The beforeSurveyActivate event.
     * @param string $openRouterKey The openrouter api key from the settings.
     * @param Survey $survey The survey being activated.
     * @param array $questions The questions in the survey without the subquestions.
     * @return bool Returns true if all checks passed, false otherwise.
     */
    public static function performChecks(mixed $event, $openRouterKey, Survey $survey, array $questions): bool {
        $containsOpenRouterPrompts = false;

        foreach ($questions as $q) {
            // if this is not llm question -> no need to check anything
            if (!functions::isPlaceholderQuestion($q)) {
                continue;
            }

            // for each language
            foreach ($survey->allLanguages as $lang) {
                // get array object from question's text
                $questionData = functions::getPlaceholderQuestionArray($lang, $q);
                if (!$questionData) {
                    // could not parse questions text - not a valid json
                    $event->set("success", false);
                    $event->set("message", "The question $q->title is marked as an llm question but the question's text could not be parsed as a json object for the language $lang.");
                    return false;
                }
                // check llm parameters for each prompt item
                foreach ($questionData as $item) {
                    if (empty($item["type"])) {
                        // object is missing the field "type"
                        $event->set("success", false);
                        $event->set("message", "Please provide the field 'type' with every entry in question $q->title's text for the language $lang.");
                        return false;
                    }
                    if ($item["type"] == "prompt") {
                        // if object type is prompt
                        if (empty($item["model"])) {
                            $event->set("success", false);
                            $event->set("message", "Please provide a model with each prompt in question $q->title for the language $lang.");
                            return false;
                        }
                        if (!empty($item["temperature"]) && ($item["temperature"] < 0 || $item["temperature"] > 2)) {
                            $event->set("success", false);
                            $event->set("message", "Please choose a temperature between 0 and 2 for question $q->title for the language $lang.");
                            return false;
                        }
                        if (!empty($item["maxTokens"]) && $item["maxTokens"] <= 0) {
                            $event->set("success", false);
                            $event->set("message", "Please choose more than 0 max tokens in question $q->title for the language $lang.");
                            return false;
                        }
                        if (!empty($item["topP"]) && ($item["topP"] < 0 || $item["topP"] > 1)) {
                            $event->set("success", false);
                            $event->set("message", "Please choose a top P value between 0 or 1 for question $q->title for the language $lang.");
                            return false;
                        }
                        $containsOpenRouterPrompts = true;
                    }
                }
            }
        }
        // check if survey contains normal openrouter prompts and the api key is set in the settings
        if ($containsOpenRouterPrompts && empty($openRouterKey)) {
            $event->set("success", false);
            $event->set("message", "Please set an API key in the plugin settings for the survey.");
            return false;
        }

        return true;
    }

    /**
     * Inserts a new hidden question into the survey.
     * Returns if the insertion was successful.
     * Adds an attribute to mark this question as an automatically added save question.
     * @param int $surveyId The survey id.
     * @param int $groupId The group id where to insert the question.
     * @param string $type The question type.
     * @param int $pos The position to insert the question at. -1 to insert at the end.
     * @param string $title The question title.
     * @param string $text The question text.
     * @param string $language The base language of the survey.
     * @return bool Returns true if the insertion was successful, false otherwise.
     */
    public static function insertQuestion($surveyId, $groupId, $type = "T", $pos = -1, $title, $text, $language): bool {
        // create new question object
        $newQuestion = new Question();
        $newQuestion->sid = $surveyId;
        $newQuestion->gid = $groupId;
        $newQuestion->type = $type;
        $newQuestion->title = $title;
        $newQuestion->question_order = $pos == -1 ? $newQuestion->getHighestQuestionOrderNumberInGroup($groupId) + 1 : $pos;
        $newQuestion->mandatory = "N";
        $newQuestion->other = "N";
        $newQuestion->relevance = "1";

        if ($newQuestion->save()) {
            // set question text and help text
            $newQLang = new QuestionL10n();
            $newQLang->qid = $newQuestion->qid;
            $newQLang->language = $language;
            $newQLang->question = $text;
            $newQLang->help = "This question was automatically inserted by the plugin LLMSurvey. It is hidden from the user and used to store information regarding the LLM request. As long as the plugin is active, the question will reappear every time you activate the survey.";
            $newQLang->save();

            // set question hidden
            $newQAttr = new QuestionAttribute();
            $newQAttr->qid = $newQuestion->qid;
            $newQAttr->attribute = "hidden";
            $newQAttr->value = "1";
            $newQAttr->save();

            // set attribute to mark question for later identification
            $qAttr = new QuestionAttribute();
            $qAttr->qid = $newQuestion->qid;
            $qAttr->attribute = "llmSurveySave";
            $qAttr->value = "1";
            $qAttr->save();

            LimeExpressionManager::SetDirtyFlag();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if the question is marked as an LLM question.
     * @param Question $question The question to check.
     * @return bool Returns true if the question is a placeholder question, false otherwise.
     */
    public static function isPlaceholderQuestion(Question $question): bool {
        $placeholderQuestion = QuestionAttribute::model()->find("qid=:qid AND attribute=:attribute", array(":qid" => $question->qid, ":attribute" => "llmElement"));
        return $placeholderQuestion->value == 1;
    }

    /**
     * Returns if there are LLM questions present in the specified group.
     * @param QuestionGroup $group The question group to check.
     * @return bool Returns true if there is at least one placeholder question in the group, false otherwise.
     */
    public static function placeholderQuestionInGroup(QuestionGroup $group): bool {
        $questions = $group->questions;
        foreach ($questions as $q) {
            if (self::isPlaceholderQuestion($q)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if the question is an automatically added, hidden question and is used to save the relevant LLM data by checking the attribute.
     * @param Question $question The question to check.
     * @return bool Returns true if the question is a save question, false otherwise.
     */
    public static function isSaveQuestion(Question $question): bool {
        $saveQuestion = QuestionAttribute::model()->find("qid=:qid AND attribute=:attribute", array(":qid" => $question->qid, ":attribute" => "llmSurveySave"));
        return $saveQuestion->value == 1;
    }

    /**
     * Updates the question order of a question.
     * Does nothing if the question is a subquestion.
     * @param Question $question The question to update.
     * @param int $newQuestionOrder The new position within the group.
     */
    public static function updateQuestionOrder(Question $question, int $newQuestionOrder) {
        if (isset($question->parent)) {
            return;
        }
        $question->question_order = $newQuestionOrder;
        $question->save();
    }

    /**
     * Returns the text of a question for the specified language.
     * @param string $language The language code.
     * @param Question $question The question to get the text from.
     * @return string|false The question text or false if the language was not found.
     */
    static function getQuestionText(string $language, Question $question): string | false {
        foreach ($question->questionl10ns as $langOption) {
            if ($langOption->language == $language) {
                return $langOption->question;
            }
        }
        return false;
    }

    /**
     * Expects a json string in the question's text.
     * Removes all html tags outside of strings and cleans up not allowed characters.
     * Decodes the cleaned string into an associative array.
     * @param string $language The language code to get the question text for.
     * @param Question $question The question to get the text from.
     * @return array The text as an associative array.
     */
    public static function getPlaceholderQuestionArray(string $language, Question $question): array {
        $questionText = self::getQuestionText($language, $question);
        if (!$questionText) {
            return [];
        }

        // filter out html tags but not inside json strings
        // matches two cases:
        // 1. a complete json string starting and ending with " characters
        // 2. every html tag starting and ending with the characters <>
        $pattern = '/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|<[^>]+>/s';
        $cleanedQuestionText = preg_replace_callback(
            $pattern,
            function ($match) {
                // match[0] is the found text (either string or html tag)
                if ($match[0][0] === '"') {
                    // if match starts with " it is a json string, just return it
                    return preg_replace("/\s*[\r\n]+\s*/", " ", $match[0]);
                }

                // otherwise it is an html tag, remove it
                return "";
            },
            $questionText
        );

        //$cleanedQuestionText = preg_replace("/<\/?[a-zA-Z0-9]+>/", "", $questionText); // remove all html tags
        $cleanedQuestionText = preg_replace("/[^\S \t\n\r]/u", " ", $cleanedQuestionText); // remove characters that are not valid in json
        $questionPromptData = json_decode($cleanedQuestionText, true);

        if ($questionPromptData == null) {
            return array();
        }

        return $questionPromptData;
    }
}
