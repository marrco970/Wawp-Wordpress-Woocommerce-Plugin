// awp-tour.js

document.addEventListener("DOMContentLoaded", function () {
    // Access localized data from the global awpTourData object
    const steps = awpTourData.steps;
    const backText = awpTourData.backText;
    const nextText = awpTourData.nextText;
    const finishTourText = awpTourData.finishTourText; // Corrected to finishTourText as per PHP localization
    const isRTL = awpTourData.isRTL;

    let currentStep = 0;

    const createTourElements = () => {
        const overlay = document.createElement("div");
        overlay.className = "awp-tour-overlay";

        const tooltip = document.createElement("div");
        tooltip.className = "awp-tour-tooltip";

        tooltip.innerHTML = `
            <div class="awp-tour-content-panel">
                <div class="awp-tour-title"></div>
                <div class="awp-tour-message"></div>
            </div>
            <div class="awp-tour-footer">
                <div class="awp-tour-progress"></div>
                <div class="awp-tour-buttons">
                    <button class="awp-btn secondary" id="awp-tour-back">${backText}</button>
                    <button class="awp-btn primary" id="awp-tour-next">${nextText}</button>
                </div>
            </div>
            <div class="awp-tour-close">×</div>
        `;

        document.body.appendChild(overlay);
        document.body.appendChild(tooltip);

        // Add a class to make the overlay and tooltip visible with animation
        setTimeout(() => {
            overlay.classList.add("visible");
            tooltip.classList.add("visible");
        }, 10);

        return { overlay, tooltip };
    };

    const { overlay, tooltip } = createTourElements();
    const titleEl = tooltip.querySelector(".awp-tour-title");
    const messageEl = tooltip.querySelector(".awp-tour-message");
    const progressEl = tooltip.querySelector(".awp-tour-progress");
    const backButton = document.getElementById("awp-tour-back");
    const nextButton = document.getElementById("awp-tour-next");
    const closeButton = tooltip.querySelector(".awp-tour-close");

    // Function to update the progress dots
    const updateProgress = (index) => {
        progressEl.innerHTML = steps
            .map((_, i) => `<span class="awp-tour-progress-dot${i === index ? ' active' : ''}"></span>`)
            .join("");
    };

    const renderStep = (index) => {
        const step = steps[index];
        let target = null;

        // Attempt to find the target element based on selector or title
        if (step.selector) {
            target = document.querySelector(step.selector);
            if (!target && step.title) {
                // If direct selector fails, try to find an .awp-card by its title
                const allAwpCards = document.querySelectorAll('.awp-card');
                for (const card of allAwpCards) {
                    const cardTitleElement = card.querySelector('.card-title');
                    // Trim whitespace and compare
                    if (cardTitleElement && cardTitleElement.textContent.trim() === step.title.trim()) {
                        target = card;
                        break;
                    }
                }
            }

            if (!target) {
                console.warn(`AWP Tour: Target element not found for selector: ${step.selector} or title: "${step.title}". Displaying tooltip in center.`);
            }
        }

        // Hide tooltip for animation before repositioning
        tooltip.classList.remove("visible");

        // A small delay for the "fade-out" effect
        setTimeout(() => {
            // Reset tooltip positions
            tooltip.style.top = "";
            tooltip.style.left = "";
            tooltip.style.right = "";
            tooltip.style.bottom = "";
            tooltip.style.transform = "";

            if (target) {
                const rect = target.getBoundingClientRect();
                const scrollY = window.scrollY;
                const scrollX = window.scrollX;
                const padding = 10;
                const top = rect.top + scrollY - padding;
                const left = rect.left + scrollX - padding;
                const width = rect.width + padding * 2;
                const height = rect.height + padding * 2;

                // Adjust clipPath for RTL if necessary, though polygon should handle it
                overlay.style.clipPath = `polygon(
                    0 0, 100% 0, 100% 100%, 0 100%, 0 0,
                    ${left}px ${top}px,
                    ${left}px ${top + height}px,
                    ${left + width}px ${top + height}px,
                    ${left + width}px ${top}px,
                    ${left}px ${top}px
                )`;

                // Position the tooltip based on the target and available space
                if (step.position === "right") {
                    if (isRTL) {
                        tooltip.style.top = `${top + height / 2}px`;
                        tooltip.style.right = `${window.innerWidth - (left + width) + 20}px`; // Position to the left of the target
                        tooltip.style.transform = "translateY(-50%)";

                        const maxLeft = tooltip.getBoundingClientRect().left;
                        // If tooltip goes off-screen to the left, flip it to the right
                        if (maxLeft < 0) {
                            tooltip.style.right = "auto";
                            tooltip.style.left = `${left + width + 20}px`; // Position to the right of the target
                            tooltip.style.transform = "translateY(-50%) translateX(-100%)"; // Adjust for right positioning
                        }
                    } else { // LTR
                        tooltip.style.top = `${top + height / 2}px`;
                        tooltip.style.left = `${left + width + 20}px`;
                        tooltip.style.transform = "translateY(-50%)";

                        const maxRight = tooltip.getBoundingClientRect().right;
                        // If tooltip goes off-screen to the right, flip it to the left
                        if (maxRight > window.innerWidth) {
                            tooltip.style.left = "auto";
                            tooltip.style.right = `${window.innerWidth - left + 20}px`;
                            tooltip.style.transform = "translateY(-50%) translateX(100%)"; // Adjust for left positioning
                        }
                    }
                } else if (step.position === "center") {
                    overlay.style.clipPath = "none"; // Remove highlight for center steps
                    tooltip.style.top = "50%";
                    tooltip.style.left = "50%";
                    tooltip.style.transform = "translate(-50%, -50%)";
                }
            } else {
                // If there's no target, show the tooltip in the center
                overlay.style.clipPath = "none";
                tooltip.style.top = "50%";
                tooltip.style.left = "50%";
                tooltip.style.transform = "translate(-50%, -50%)";
            }

            // Update content and re-show with animation
            titleEl.innerHTML = step.title || ""; // Set title, default to empty string if not provided
            messageEl.innerHTML = step.message;
            updateProgress(index);

            // Update button visibility and text
            backButton.style.display = index === 0 ? "none" : "inline-block";
            nextButton.textContent = index === steps.length - 1 ? finishTourText : nextText;

            tooltip.classList.add("visible"); // Show tooltip with animation
        }, 300); // Wait for the fade-out transition
    };

    const endTour = () => {
        // Add classes to trigger fade-out animation
        overlay.classList.remove("visible");
        tooltip.classList.remove("visible");

        // No localStorage or sessionStorage is used here, so the tour will always reopen on page reload.

        // Remove elements after the animation completes
        setTimeout(() => {
            overlay.remove();
            tooltip.remove();
        }, 500); // Matches the CSS transition duration
    };

    renderStep(currentStep);

    nextButton.addEventListener("click", () => {
        currentStep++;
        if (currentStep >= steps.length) {
            endTour();
        } else {
            renderStep(currentStep);
        }
    });

    backButton.addEventListener("click", () => {
        if (currentStep > 0) {
            currentStep--;
            renderStep(currentStep);
        }
    });

    closeButton.addEventListener("click", () => {
        endTour();
    });
});
