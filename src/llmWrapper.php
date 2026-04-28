<?php

require_once "endpoint.php";

class llmWrapper {
    public static $defaultSystemPrompt = 'You must answer exclusively in the language identified by the language code "[startlanguage]". The code follows ISO-639-1 (e.g., "en", "de", "fr").';

    /**
     * Sends the prompt to the OpenRouter endpoint and returns the response.
     * @param string $openRouterKey The API key for OpenRouter.
     * @param string $model The models OpenRouter-code to use.
     * @param string $systemPrompt The system prompt.
     * @param string $prompt The prompt.
     * @param mixed $temperature The temperature setting for the model.
     * @param mixed $maxTokens The maximum number of tokens to generate.
     * @param mixed $topP The top_p parameter for the model.
     * @return array The response from the LLM or an error message.
     */
    public static function getLLMOutput(string $openRouterKey, string $model, string $systemPrompt, string $prompt, mixed $temperature, mixed $maxTokens, mixed $topP): array {
        $payloadArr = [
            "model" => $model,
            "messages" => [["role" => "system", "content" => $systemPrompt], ["role" => "user", "content" => $prompt]]
        ];
        if (isset($temperature) && $temperature != "") {
            $payloadArr["temperature"] = (float) $temperature;
        }
        if (isset($maxTokens) && $maxTokens != "") {
            $payloadArr["max_tokens"] = (int) $maxTokens;
        }
        if (isset($topP) && $topP != "") {
            $payloadArr["top_p"] = (float) $topP;
        }
        $payload = json_encode($payloadArr);

        $ch = curl_init(endpoint::$URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $openRouterKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout in seconds

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            unset($ch);
            return array("error" => "ERROR: The api call failed.");
        }
        unset($ch);

        $respObj = json_decode($result);
        if ($respObj == null || ($respObj->code !== null && $respObj->code !== 200)) {
            return array("error" => "An error occurred while processing the received data: " . $respObj ? $respObj->metadata->raw : "Unknown error");
        }
        if ($respObj->error) {
            return array("error" => "ERROR " . $respObj->error->code . ": " . $respObj->error->message);
        }

        $response = $respObj->choices[0]->message->content;
        return self::parseResponse($response);
    }

    /**
     * Sends the request body with the headers to the specified LLM endpoint and returns the response.
     * @param string $curlUrl The URL of the LLM endpoint.
     * @param array $requestHeaders An array of headers to include in the request.
     * @param array $requestBody The body of the request to send.
     * @param mixed $curlAnswerLocation The location path in the response where the generated content can be found.
     * @return array The response from the LLM or an error message.
     */
    public static function getCustomLLMOutput(string $curlUrl, array $requestHeaders, array $requestBody, mixed $curlAnswerLocation): array {
        $payload = json_encode($requestBody);

        $ch = curl_init($curlUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            unset($ch);
            return array("error" => "ERROR: The api call failed.");
        }
        unset($ch);

        $respObj = json_decode($result, true);
        if ($respObj == null || ($respObj["code"] !== null && $respObj["code"] !== 200)) {
            return array("error" => "An error occurred while processing the received data: " . $respObj ? $respObj["metadata"]["raw"] : "Unknown error");
        }
        if ($respObj["error"]) {
            return array("error" => "ERROR " . $respObj["error"]["code"] . ": " . $respObj["error"]["message"]);
        }

        // get user specified response location
        $path = self::findResponsePath($curlAnswerLocation);
        if ($path === null) {
            return array("error" => "ERROR: Could not parse the specified response location.");
        }

        // get the value at the specified path
        $value = self::getValueAtPath($respObj, $path);
        return self::parseResponse($value);
    }

    /**
     * Iterates through the provided template path and searches for the the token '[RESPONSE]'.
     * Returns the path to the response field.
     * @param template The template path to search through.
     * @param currentPath The current path during the recursion.
     */
    static function findResponsePath($template, $currentPath = []): array|null {
        if ($template == "[RESPONSE]") {
            return $currentPath;
        }

        if (is_array($template)) {
            // Arrays: numeric or associative
            foreach ($template as $key => $value) {
                $result = self::findResponsePath($value, array_merge($currentPath, [$key]));
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Returns the value of the object at the given path.
     * @param mixed $obj The response object.
     * @param array $path The path to the value.
     * @return string The value at the specified path or an error message in JSON format.
     */
    static function getValueAtPath($obj, array $path): string {
        $current = $obj;
        foreach ($path as $key) {
            if (!isset($current[$key])) {
                return '{"error": "Could not get value at specified postion in the response."}';
            }
            $current = $current[$key];
        }
        return $current;
    }

    /**
     * Checks if the response is in the correct format.
     * If its not a valid JSON, it tries to find the brackets indicating the object and isolates it.
     * Returns the associative array containing the LLM answers.
     * @param mixed $response The response from the LLM.
     * @return array The parsed response or an error message.
     */
    static function parseResponse(mixed $response): array {
        // try to fix if response is like: '```json{"field1" : "ans1", "field2": "ans2", ...]```'
        $fixedResponse = $response;
        if (!is_array($response)) {
            if (str_contains($response, "{") && str_contains($response, "}")) {
                // if string contains opening and closing bracket, try to extract the JSON object
                $fixedResponse = "{" . explode("{", $response)[1];
                $fixedResponse = explode("}", $fixedResponse)[0] . "}";
            }
        }
        if ($parsedData = json_decode($fixedResponse, true)) {
            return $parsedData;
        } else {
            return array("error" => "ERROR: Could not parse received data.", "fixedResponse" => $fixedResponse, "rawData" => $response);
        }
    }

    /**
     * Reads the prompt and replaces placeholders with actual response data.
     * @param int $surveyId The survey ID.
     * @param string $prompt The prompt (with placeholders).
     * @param array $response The response data as an associative array to replace placeholders.
     * @param string $lang The language code.
     * @return string The processed prompt with replaced placeholders.
     */
    public static function createPrompt(int $surveyId, string $prompt, array $response, string $lang): string {
        foreach ($response as $key => $value) {
            $placeholder = "[$key]";
            $prompt = str_replace($placeholder, self::parseValue($surveyId, $key, $value, $lang), $prompt);
        }
        return $prompt;
    }

    /**
     * Iterates over the body object and replaces the placeholders with data from the response array.
     * @param int $surveyId The survey ID.
     * @param array $body The body object (with placeholders).
     * @param array $response The response data as an associative array to replace placeholders.
     * @param string $lang The language code.
     * @return array The processed body with replaced placeholders.
     */
    public static function createBody(int $surveyId, array $body, array $response, string $lang): array {
        $body = self::replacePlaceholdersRecursive($surveyId, $body, $response, $lang);
        return $body;
    }

    /**
     * Helper function to find the placeholders within the fields of a body and replace them with data from the response array.
     * @param int $surveyId The survey ID.
     * @param mixed $data The current data to process (can be array, object, or string).
     * @param array $response The response data as an associative array to replace placeholders.
     * @param string $lang The language code.
     * @return mixed The processed data with replaced placeholders.
     */
    private static function replacePlaceholdersRecursive(int $surveyId, $data, array $response, string $lang) {
        // If value is an array -> recurse into each entry
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::replacePlaceholdersRecursive($surveyId, $value, $response, $lang);
            }
            return $data;
        }

        // If value is an object -> convert or handle properties
        if (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->{$key} = self::replacePlaceholdersRecursive($surveyId, $value, $response, $lang);
            }
            return $data;
        }

        // If value is a string -> apply replacements
        if (is_string($data)) {
            foreach ($response as $respKey => $respValue) {
                $placeholder = "[$respKey]";
                $data = str_replace($placeholder, self::parseValue($surveyId, $respKey, $respValue, $lang), $data);
            }
        }

        // Everything else (numbers, bools) remains the same
        return $data;
    }

    /**
     * Transforms numbers to a string representation and the single character answers for certain question types to human-readable strings.
     * Also replaces answer codes with the actual answer text.
     * @param int $surveyId The survey ID.
     * @param mixed $key The question code.
     * @param mixed $value The answer value.
     * @param string $lang The language code.
     * @return string The parsed value.
     */
    static function parseValue(int $surveyId, mixed $key, mixed $value, string $lang) {
        // check if value is an answer code and if so, replace
        $keys = explode("_", $key);
        // if this is NOT a subquestion and the value is empty -> return 'N/A'
        if (count($keys) == 1 && ($value == null || $value == "")) {
            return "N/A";
        }

        // get survey and question objects
        $survey = Survey::model()->findByPk($surveyId);
        $question = null;
        foreach ($survey->allQuestions as $q) {
            if ($q->title == $keys[0]) {
                $question = $q;
                break;
            }
        }
        if (!$question) {
            // if the question could not be found -> return the raw value
            return $value;
        }

        // if multiple choice question and value is empty
        if (count($keys) > 1 && $question->type != "M" && $question->type != "P" && ($value == null || $value == "")) {
            return "";
        }

        // check if the value corresponds to an answer code
        $answer = null;
        foreach ($question->answers as $a) {
            if ($a->code == $value) {
                $answer = $a;
                break;
            }
        }
        // if answer code found, return the answer text in the current language
        if ($answer) {
            $answerStr = "";
            foreach ($answer->answerl10ns as $aLang) {
                // get the answer in the current language of the survey
                if ($aLang->language == $lang) {
                    $answerStr = $aLang->answer;
                    break;
                }
            }
            return $answerStr;
        }

        if (($question->type == "L" || $question->type == "!") && $value == "-oth-") { // List (radio) || Bootstrap Dropdown and other field was selected
            // if other field was selected (value = -oth-) -> don't return anything here because the other text is in a different field
            return "";
        }
        if ($question->type == "E") { // array inc same dec
            if ($value == "I") {
                return "Increase";
            }
            if ($value == "S") {
                return "Same";
            }
            if ($value == "D") {
                return "Decrease";
            }
            return $value;
        }
        if ($question->type == "C") { // array yes uncertain no
            if ($value == "Y") {
                return "Yes";
            }
            if ($value == "U") {
                return "Uncertain";
            }
            if ($value == "N") {
                return "No";
            }
            return $value;
        }
        if ($question->type == "M" || $question->type == "P") { // multiple choice || multiple choice with comment
            if (str_contains($keys[1], "comment") || $keys[1] == "other") {
                // if comment or other field -> return raw value
                return $value;
            }
            if ($value == "Y") {
                return "Yes";
            } else {
                return "No";
            }
        }
        if ($question->type == "G") { // gender
            if ($value == "F") {
                return "Female";
            }
            if ($value == "M") {
                return "Male";
            }
            return "N/A";
        }
        if ($question->type == "N" || $question->type == "K") { // numerical || multiple numerical
            return number_format($value);
        }
        if ($question->type == "Y") { // yes no radio
            if ($value == "Y") {
                return "Yes";
            }
            if ($value == "N") {
                return "No";
            }
            return "No answer";
        }
        return $value;
    }
}
