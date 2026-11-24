
const vincularForm = document.getElementById("vincular-form");
const vincularMessage = document.getElementById("vincular-message");
const vincularButton = document.getElementById("vincular-button");
const myCode = document.getElementById("my-code");

async function cargarDatos() {
    try {
        const response = await fetch("api/check_login.php", {
            credentials: "include"
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error("No se ha podido comprobar la sesión.");
        }

        if (!result.data.logged) {
            window.location.href = "login.html";
            return;
        }

        if (result.data.tiene_pareja) {
            window.location.href = "app.html";
            return;
        }

        myCode.textContent = result.data.usuario.codigo;

    } catch (error) {
        myCode.textContent = "No disponible";
        vincularMessage.textContent = error.message;
    }
}

// Consultar el código al abrir la pantalla
cargarDatos();

// Enviar el código de la pareja
vincularForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const codigo = document.getElementById("codigo")
        .value.trim().toUpperCase();

    vincularMessage.textContent = "";

    if (!/^[A-Z]{3}[0-9]{4}$/.test(codigo)) {
        vincularMessage.textContent =
            "El código debe tener 3 letras y 4 números.";
        return;
    }

    vincularButton.disabled = true;
    vincularButton.textContent = "Vinculando...";

    try {
        const response = await fetch("api/pareja_vincular.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            credentials: "include",
            body: JSON.stringify({ codigo: codigo })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || "No se ha podido vincular la pareja."
            );
        }

        vincularMessage.style.color = "#43845A";
        vincularMessage.textContent =
            "¡Pareja vinculada correctamente!";

        setTimeout(() => {
            window.location.href = "app.html";
        }, 1500);

    } catch (error) {
        vincularMessage.style.color = "#b54747";
        vincularMessage.textContent = error.message;
    } finally {
        vincularButton.disabled = false;
        vincularButton.textContent = "Vincular pareja";
    }
});
