window.addEventListener("DOMContentLoaded", main);

/**
 * Main function to handle subquestion text replacement.
 * This function looks for data markers in the DOM which contain the LLM output for each question.
 * It then finds all subquestions related to that question and replaces any placeholders in the subquestion text with the corresponding LLM output.
 */
function main() {
  // looks for the data markers containing the LLM output for each question
  const dataMarkers = document.querySelectorAll(".llmSurveyDataMarker");

  console.log(`Found ${dataMarkers.length} data markers`);

  dataMarkers.forEach((data) => {
    // find parent question element with id question{id}
    let parentElement = data;
    let limit = 10;
    let found = false;
    while (limit > 0 && !found) {
      if (parentElement.id) {
        const match = parentElement.id.match(/question\d+/);
        if (match) {
          found = true;
          break;
        }
      }

      limit--;
      parentElement = parentElement.parentElement;
    }

    const subquestions = getAllSubquestions(parentElement);
    const outputData = JSON.parse(data.dataset.llmoutputs);

    for (const sq of subquestions) {
      // iterate over each subquestion and look for placeholders in the text and replace if necessary
      replaceSubquestionText(sq, outputData);
    }
  });
}

/**
 * Searches for the dom elements defining a subquestion or answer option in an element.
 * @param {Element} element The parent question element which the subquestions are searched from.
 * @returns A list of dom elements for subquestions and answers.
 */
function getAllSubquestions(element) {
  return element.querySelectorAll(
    ".answers-list, .radio-list, .subquestion-list, .answer-item, .checkbox-text-item, .bootstrap-buttons-div, select > option, .answertext, .answer-text, .answer-item",
  );
}

/**
 * Replaces the placeholders in the
 * @param {string} sq subquestions text with
 * @param {string[]} llmOutputs the generated outputs from the LLM.
 */
function replaceSubquestionText(sq, llmOutputs) {
  let sqText = sq.querySelector("th.answertext");

  if (!sqText && (sq.nodeName == "TH" || (sq.nodeName == "LI" && sq.classList.contains("sortable-item")))) {
    // handle ranking question type
    sqText = sq;
  }

  if (sqText) {
    // handle normal subquestion text replacement
    sqText.innerHTML = replacePlaceholders(sqText.innerHTML, llmOutputs);
    return;
  }

  const btnText = sq.querySelector("label");
  if (btnText) {
    // handle button text replacement
    btnText.innerHTML = replacePlaceholders(btnText.innerHTML, llmOutputs);
    return;
  }

  sq.innerHTML = replacePlaceholders(sq.innerHTML, llmOutputs);
}

/**
 * Replaces all placeholders in the text with the data from the outputs array.
 * @param {string} text The text with the placeholders.
 * @param {string[]} outputs The array with the LLM outputs.
 * @returns An updated text where the placeholders are replaced by the LLM output data.
 */
function replacePlaceholders(text, outputs) {
  let newText = text;
  for (const [key, value] of Object.entries(outputs)) {
    const placeholder = `[${key}]`;
    newText = newText.replaceAll(placeholder, value);
  }
  return newText;
}
