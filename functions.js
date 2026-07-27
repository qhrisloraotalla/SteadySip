window.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("button[data-popup]").forEach(button => {
        button.addEventListener("click", () => {
            const popupId = button.dataset.popup;
            const action = button.dataset.action;

            if (action === "show") showPopup(popupId);
            else if (action === "hide") hidePopup(popupId);
        });
    });

    // Add event listener for clicking outside popup
    document.addEventListener("click", (event) => {
        document.querySelectorAll(".popup").forEach(popup => {
            const isClickInside = popup.contains(event.target);
            const isButton = event.target.matches("button[data-popup]");

            // If popup is open, and user clicked outside both popup and its buttons → close it
            if (!isClickInside && !isButton && popup.style.display === "block") {
                hidePopup(popup.id);
            }
        });
    });
});

function showPopup(id) {
    const popup = document.getElementById(id);
    if (popup) {
        popup.style.display = "block";
        document.body.classList.add("popup-active");
    }
}

function hidePopup(id) {
    const popup = document.getElementById(id);
    if (popup) {
        popup.style.display = "none";
        document.body.classList.remove("popup-active");
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const supplyDropdown = document.getElementById('supply_id');
    const unitDisplay = document.getElementById('unit_display');

    if (!supplyDropdown || !unitDisplay) return;

    // When the user changes selection
    supplyDropdown.addEventListener('change', () => {
        const selectedOption = supplyDropdown.options[supplyDropdown.selectedIndex];
        const unit = selectedOption.getAttribute('data-unit');
        unitDisplay.textContent = unit ? unit : '';
    });

    // Optional: if an item is pre-selected, show the unit immediately
    const initialOption = supplyDropdown.options[supplyDropdown.selectedIndex];
    const initialUnit = initialOption?.getAttribute('data-unit');
    if (initialUnit) unitDisplay.textContent = initialUnit;
});
