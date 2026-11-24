
const form = document.getElementById("register-form");
const message = document.getElementById("register-message");
const button = document.getElementById("register-button");

form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const nombre = document.getElementById("nombre").value.trim();
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirm-password").value;

    message.textContent = "";

    // Comprobar que las contraseñas coinciden
    if (password !== confirmPassword) {
        message.textContent = "Las contraseñas no coinciden.";
        return;
    }

    // Validar la contraseña según las reglas del Back-End
    if (password.length < 4 || !/[A-Z]/.test(password)) {
        message.textContent =
            "La contraseña necesita 4 caracteres y al menos una mayúscula.";
        return;
    }

    button.disabled = true;
    button.textContent = "Creando cuenta...";

    try {
        const response = await fetch("api/register.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            credentials: "include",
            body: JSON.stringify({
                nombre: nombre,
                email: email,
                password: password
            })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "No se pudo crear la cuenta.");
        }

        message.textContent =
            "¡Cuenta creada! Tu código personal es: " +
            result.data.codigo;

        form.reset();

        // Llevar al usuario al login después de unos segundos
        setTimeout(() => {
            window.location.href = "login.html";
        }, 4000);

    } catch (error) {
        message.textContent = error.message;
    } finally {
        button.disabled = false;
        button.textContent = "Crear mi cuenta";
    }
});
