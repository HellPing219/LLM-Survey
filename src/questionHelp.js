document.addEventListener("DOMContentLoaded", main); // handle full page reload
main(); // handle partial page loads

/**
 * Initializes the question help functionality.
 * The help element is always added but its visibility is toggled based on the LLM question switch.
 */
function main() {
  // always add help element but toggle visibility
  addHelpElement();

  // check if llm question switch is activated by getting button elements
  const inputElementOn = document.getElementById("advancedSettings[llmsurvey][llmElement]_1");
  const inputElementOff = document.getElementById("advancedSettings[llmsurvey][llmElement]_0");
  if (!inputElementOn || !inputElementOff) {
    return;
  }
  inputElementOn.addEventListener("click", () => toggleVisibility(true));
  inputElementOff.addEventListener("click", () => toggleVisibility(false));

  // handle initial state
  if (inputElementOn.checked) {
    toggleVisibility(true);
  }
}

/**
 * Adds the help element to the DOM if it doesn't already exist.
 */
function addHelpElement() {
  // check if help element already on page
  const helpElementCheck = document.getElementById("llmSurveyHelpElement");
  if (helpElementCheck) {
    return;
  }

  // find edit question tab element to append to
  const questionInputElement = document.querySelector(".tab-content");
  if (!questionInputElement) {
    return;
  }

  // add box with explanation and example
  const exampleJson = [
    {
      type: "prompt",
      model: "your OpenRouter model code",
      temperature: "optional, 0-2",
      maxTokens: "optional, >0",
      topP: "optional, 0-1",
      data: "Your prompt. Here you can use placeholders to insert the answers from previous survey questions or previously generated llm outputs. IMPORTANT: Here you need to tell the llm what you would like the output to look like. For the plugin to function, it needs to be a valid json, but it is your choice what you want the llm to name the fields. For example add 'Your output must be a valid json in the format {ITEM1: item1, ITEM2: item2, ITEM3: item3}' to your prompt. You can then display the generated data using the placeholders [ITEM1], [ITEM2] or [ITEM3] in a question text or use them in a later prompt.",
      overrideSystemprompt:
        "true | false, // optional, set this option to true to prevent the survey-wide system prompt from being sent along with this prompt",
    },
    {
      type: "text",
      data: "Your question text. This is the text normally displayed when filling in this text field. Here and in the subquestions / answers (if there are any for the selected question type) you can use the discussed placeholder to display your data to the user.",
    },
  ];
  const helpElement =
    '<div id="llmSurveyHelpElement" style="color:gray;display:none;"><p>You\'ve selected this question as an LLM output question.</p>' +
    "<p>Please enter a json string in the field above where you can define your prompts and the displayed text for this question. In the array you can input multiple prompts but only one text element. See <a href='https://git.se.uni-hannover.de/llm-survey-tool/llmsurvey/-/blob/main/docs/prompt-codes.md?ref_type=heads' target='_blank'>how to insert answers into a prompt</a></p>" +
    "<p>Use the following structure:</p>" +
    '<pre id="json">' +
    JSON.stringify(exampleJson, "\n", 2) +
    "</pre></div>";

  questionInputElement.innerHTML = questionInputElement.innerHTML + helpElement;
}

/**
 * Shows or hides the help element.
 * @param {boolean} show - true to show, false to hide
 */
function toggleVisibility(show) {
  const helpElement = document.getElementById("llmSurveyHelpElement");
  if (!helpElement) {
    return;
  }
  helpElement.style.display = show ? "block" : "none";
}
