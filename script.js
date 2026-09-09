document.addEventListener("DOMContentLoaded", function () {

    const menuButton = document.getElementById("mobileMenuBtn");
    const mainNav = document.getElementById("mainNav");

    if (menuButton && mainNav) {

        menuButton.addEventListener("click", function () {

            mainNav.classList.toggle("show");

            const icon = menuButton.querySelector("i");

            if (mainNav.classList.contains("show")) {
                icon.classList.remove("fa-bars");
                icon.classList.add("fa-xmark");
            } else {
                icon.classList.remove("fa-xmark");
                icon.classList.add("fa-bars");
            }

        });


        // Close menu after selecting a link
        mainNav.querySelectorAll("a").forEach(function (link) {

            link.addEventListener("click", function () {

                mainNav.classList.remove("show");

                const icon = menuButton.querySelector("i");

                icon.classList.remove("fa-xmark");
                icon.classList.add("fa-bars");

            });

        });

    }


    // Prevent appointment date from selecting a past date
    const dateInput = document.getElementById("appointment-date");

    if (dateInput) {

        const today = new Date();

        const year = today.getFullYear();

        const month = String(today.getMonth() + 1).padStart(2, "0");

        const day = String(today.getDate()).padStart(2, "0");

        dateInput.min = `${year}-${month}-${day}`;
    }

});