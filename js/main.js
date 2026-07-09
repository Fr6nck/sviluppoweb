/* Studio Nova — one-page site interactions */
(function () {
  "use strict";

  /* ---------- Mobile navigation ---------- */
  var toggle = document.querySelector(".nav__toggle");
  var menu = document.getElementById("nav-menu");

  toggle.addEventListener("click", function () {
    var isOpen = menu.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", isOpen);
    document.body.style.overflow = isOpen ? "hidden" : "";
  });

  // Close the menu when a link is clicked
  menu.addEventListener("click", function (e) {
    if (e.target.matches("a")) {
      menu.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
      document.body.style.overflow = "";
    }
  });

  /* ---------- Header shadow on scroll ---------- */
  var header = document.querySelector(".site-header");

  window.addEventListener(
    "scroll",
    function () {
      header.classList.toggle("is-scrolled", window.scrollY > 10);
    },
    { passive: true }
  );

  /* ---------- Scroll-reveal animations ---------- */
  var revealEls = document.querySelectorAll(".reveal");

  if ("IntersectionObserver" in window) {
    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15 }
    );

    revealEls.forEach(function (el) {
      observer.observe(el);
    });
  } else {
    revealEls.forEach(function (el) {
      el.classList.add("is-visible");
    });
  }

  /* ---------- Active nav link while scrolling ---------- */
  var sections = document.querySelectorAll("section[id]");
  var navLinks = document.querySelectorAll(".nav__link");

  function setActiveLink() {
    var current = "";
    sections.forEach(function (section) {
      if (window.scrollY >= section.offsetTop - 120) {
        current = section.id;
      }
    });
    navLinks.forEach(function (link) {
      link.classList.toggle(
        "is-active",
        link.getAttribute("href") === "#" + current
      );
    });
  }

  window.addEventListener("scroll", setActiveLink, { passive: true });

  /* ---------- Contact form validation ---------- */
  var form = document.getElementById("contact-form");
  var success = form.querySelector(".form__success");

  function setError(input, message) {
    var field = input.closest(".form__field");
    field.classList.toggle("has-error", Boolean(message));
    field.querySelector(".form__error").textContent = message || "";
  }

  function validateField(input) {
    var value = input.value.trim();

    if (input.required && !value) {
      setError(input, "Questo campo è obbligatorio.");
      return false;
    }

    if (input.type === "email" && value) {
      var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
      if (!emailOk) {
        setError(input, "Inserisci un indirizzo email valido.");
        return false;
      }
    }

    setError(input, "");
    return true;
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    var inputs = form.querySelectorAll("input[required], textarea[required]");
    var valid = true;

    inputs.forEach(function (input) {
      if (!validateField(input)) valid = false;
    });

    if (!valid) return;

    // No backend on a static site: simulate a successful send.
    success.hidden = false;
    form.reset();

    setTimeout(function () {
      success.hidden = true;
    }, 6000);
  });

  // Live validation once a field has been touched
  form.querySelectorAll("input[required], textarea[required]").forEach(
    function (input) {
      input.addEventListener("blur", function () {
        validateField(input);
      });
      input.addEventListener("input", function () {
        if (input.closest(".form__field").classList.contains("has-error")) {
          validateField(input);
        }
      });
    }
  );

  /* ---------- Footer year ---------- */
  document.getElementById("year").textContent = new Date().getFullYear();
})();
