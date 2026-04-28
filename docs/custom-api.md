# Custom API

### JSON Configuration Structure

For custom endpoints the structure of the question text looks different. Depending on the target model, request and response structures differ. That's why it is necessary to basically build your own *curl* command. This example is for a request to googles *gemini 2.5 flash* model:

```json
[
  {
    "type": "customApi",
    "curlUrl": "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent",
    "curlHeaders": ["x-goog-api-key: [CUSTOM_KEY]", "Content-Type: application/json"],
    "curlBody": {
      "contents": [{
        "parts": [
          {
            "text": "You are an assistant. The user is [age] years old. Generate a suggestion. Your output MUST be a valid json in the format {\"RESULT\": \"your text here\"}."
          }
        ]
      }]
    },
    "curlAnswerLocation": {
      "candidates": [{
        "content": {
          "parts": [{
            "text": "[RESPONSE]"
          }],
        },
      }],
    }
  },
  { "type": "text", "data": "Based on your profile, the AI suggests: [RESULT]" }
]
```

#### The Prompt Object

- `type`: Must be `"customApi"`.
- `curlUrl`: The endpoint to which the request is sent.
- `curlHeaders`: An array of strings representing the headers of the request. To use your previously defined custom api keys use the placeholders `[CUSTOM_KEY_1]`, `[CUSTOM_KEY_2]` or `[CUSTOM_KEY_3]`.<br>
*IMPORTANT: Do NOT write your api key manually into this field - in case something goes wrong, the key could be displayed to the user!*
- `curlBody`: Here define the body of your request. This entry is sent directly to the endpoint as the body of the request. Define your prompt in the correct place. Use placeholders like `[question_code]` to insert previous answers. For more complex question types refer to [Prompt Codes](docs/prompt-codes.md).
- **JSON Requirement & Field Naming:** You **must** explicitly tell the LLM to output JSON and which field names (keys) to use in its JSON output (e.g., `format {KEY: value}`).
  - These keys become the placeholders you will use to display the text.
  - If you want to display the output using the placeholder `[RESULT]`, your prompt must explicitly ask: *"Return a valid JSON object in the format {RESULT: your generated answer}."*
  - The plugin automatically handles conversational text (like "Here is your JSON: {...}"), but the keys inside the JSON must match your placeholders exactly.
- `curlAnswerLocation`: This field works like a template for where to expect the actual generated answer from the LLM in the response object. At the target path use the string `"[RESPONSE]"` to mark this position. The plugin will then look for this path in the response and try to process the string.

#### The Text Object

- `type`: Must be `"text"`.
- `data`: The visible text displayed to the user. You can use the keys from the LLM's JSON output (e.g., `[RESULT]`) as placeholders here.

### Examples

**Gemini**: A request for gemini looks like this:
```
curl "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent" \
  -H "x-goog-api-key: $GEMINI_API_KEY" \
  -H 'Content-Type: application/json' \
  -X POST \
  -d '{
    "contents": [
      {
        "parts": [
          {
            "text": "Explain how AI works in a few words"
          }
        ]
      }
    ]
  }'
```

And a response like this:
```
{
  "candidates": [
    {
      "content": {
        "parts": [
          {
            "text": "This is the generated text response from Gemini."
          }
        ],
        "role": "model"
      },
      "finishReason": "STOP",
      "index": 0,
      "safetyRatings": [
        {
          "category": "HARM_CATEGORY_UNSPECIFIED",
          "probability": "NEGLIGIBLE"
        }
      ]
    }
  ],
  "usageMetadata": {
    "promptTokenCount": 10,
    "candidatesTokenCount": 20,
    "totalTokenCount": 30
  }
}
```

Which can be used in the plugin with the example question text above.



**ChatGPT**: A request for ChatGPT looks like this:
```
curl "https://api.openai.com/v1/responses" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $OPENAI_API_KEY" \
  -d '{
    "model": "gpt-5",
    "input": "Write a one-sentence bedtime story about a unicorn."
  }'
```

And a response like this:
```
[
  {
    "id": "msg_67b73f697ba4819183a15cc17d011509",
    "type": "message",
    "role": "assistant",
    "content": [
      {
        "type": "output_text",
        "text": "Under the soft glow of the moon, Luna the unicorn danced through fields of twinkling stardust, leaving trails of dreams for every child asleep.",
        "annotations": []
      }
    ]
  }
]
```

Which means the question text has to look like this:
```json
[
  {
    "type": "customApi",
    "curlUrl": "https://api.openai.com/v1/responses",
    "curlHeaders": ["Content-Type: application/json", "Authorization: Bearer [CUSTOM_KEY]"],
    "curlBody": {
      "model": "gpt-5",
      "input": "Write a one-sentence bedtime story about a unicorn."
    },
    "curlAnswerLocation": [{
      "content": [{
          "text": "[RESPONSE]",
      }]
    }]
  }
]
```
