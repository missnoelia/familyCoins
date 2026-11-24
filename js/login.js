
const loginForm = document.getElementById("login-form");
const loginMessage = document.getElementById("login-message");
const loginButton = document.getElementById("login-button");

loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;

    loginMessage.textContent = "";

    loginButton.disabled = true;
    loginButton.textContent = "Comprobando...";

    try {
        const response = await fetch("api/login.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            credentials: "include",
            body: JSON.stringify({
                email: email,
                password: password
            })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || "No se ha podido iniciar sesión."
            );
        }

        // Comprobar si el usuario tiene pareja
        if (result.data.tiene_pareja) {
            window.location.href = "app.html";
        } else {
            window.location.href = "vincular.html";
        }

    } catch (error) {
        loginMessage.textContent = error.message;
    } finally {
        loginButton.disabled = false;
        loginButton.textContent = "Iniciar sesión";
    }
});
