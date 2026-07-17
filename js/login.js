(function () {
  function $(id) {
    return document.getElementById(id);
  }

  function showError(text) {
    var box = $("login-msg");
    if (!box) {
      return;
    }
    box.textContent = text;
    box.style.display = "block";
  }

  function setTheme(theme) {
    var card = $("card");
    var light = $("th-light");
    var dark = $("th-dark");

    if (!card || !light || !dark) {
      return;
    }

    if (theme === "dark") {
      card.classList.add("dark");
      dark.classList.add("active");
      light.classList.remove("active");
    } else {
      card.classList.remove("dark");
      light.classList.add("active");
      dark.classList.remove("active");
    }

    try {
      localStorage.setItem("sisgedo_theme", theme);
    } catch (error) {}
  }

  document.addEventListener("DOMContentLoaded", function () {
    var togglePass = $("togglePass");
    var pass = $("pass");
    var themeLinks = document.querySelectorAll("[data-theme]");
    var disabledActions = document.querySelectorAll(".js-disabled-access");
    var forgotLink = $("forgotLink");
    var building = document.querySelector(".building");
    var errorCode = Number(
      document.body && document.body.dataset
        ? document.body.dataset.loginError
        : 0,
    );
    var messages = {
      1: "Usuario o contraseña incorrectos.",
      2: "Demasiados intentos fallidos. Espere unos minutos e intente nuevamente.",
      3: "Ocurrió un error. Intente nuevamente.",
    };

    if (togglePass && pass) {
      togglePass.addEventListener("click", function () {
        pass.type = pass.type === "password" ? "text" : "password";
      });
    }

    themeLinks.forEach(function (link) {
      link.addEventListener("click", function (event) {
        event.preventDefault();
        setTheme(link.getAttribute("data-theme"));
      });
    });

    disabledActions.forEach(function (button) {
      button.addEventListener("click", function () {
        showError(
          "Este método de acceso aún no está disponible. Usa tu usuario y contraseña.",
        );
      });
    });

    if (forgotLink) {
      forgotLink.addEventListener("click", function (event) {
        event.preventDefault();
        showError(
          "Para recuperar tu contraseña, comunícate con el administrador del sistema.",
        );
      });
    }

    if (building) {
      building.addEventListener("error", function () {
        building.style.display = "none";
      });
    }

    try {
      if (localStorage.getItem("sisgedo_theme") === "dark") {
        setTheme("dark");
      }
    } catch (error) {}

    if (messages[errorCode]) {
      showError(messages[errorCode]);
    }
  });
})();
