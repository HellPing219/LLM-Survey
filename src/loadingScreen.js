document.addEventListener("submit", showLoadingScreen);

/**
 * Appends a loading screen with a spinner to the documents body.
 * This element is created when the form is submitted and therefore a new page is loading.
 */
function showLoadingScreen() {
  // get backgroud color
  const bgColor = getComputedStyle(document.body).backgroundColor;
  const textColor = getComputedStyle(document.body).color;
  // get primary color from root
  const primColor = getComputedStyle(document.documentElement).getPropertyValue("--bs-primary").trim();

  const styleSheet = document.createElement("style");
  styleSheet.innerText = `#loadingScreen {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: ${bgColor};
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.loader {
  width: 48px;
  height: 48px;
  border: 5px solid transparent;
  border-top: 5px solid ${primColor};
  border-radius: 50%;
  animation: spin 2s linear infinite;
  margin-bottom: 20px;
}

.loading-text {
  font-size: 16px;
  color: ${textColor};
  margin: 0;
  opacity: 0;
  animation: textFadeIn 0.2s ease 2s forwards;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

@keyframes textFadeIn {
  to { opacity: 1; }
}

#loading-screen.hidden {
  opacity: 0;
  visibility: hidden;
}
`;
  // change contents on dom
  const loadingScreenContainer = document.createElement("div");
  loadingScreenContainer.id = "loadingScreen";

  const loadingScreenSpinner = document.createElement("div");
  loadingScreenSpinner.className = "loader";

  const loadingScreenText = document.createElement("p");
  loadingScreenText.classList = "loading-text";
  loadingScreenText.innerText = "Please wait up to 15 seconds...";

  loadingScreenContainer.appendChild(loadingScreenSpinner);
  loadingScreenContainer.appendChild(loadingScreenText);

  document.head.appendChild(styleSheet);
  document.body.prepend(loadingScreenContainer);
}
