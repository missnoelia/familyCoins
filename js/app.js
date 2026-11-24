
import { loadState, saveState, dateISO } from "./storage.js";

import { PLANTS } from "./plants.js";

import {
  careTogether,
  renderGarden
} from "./garden.js";

import {
  renderShop,
  buyItem
} from "./shop.js";

import { renderAchievements } from "./achievements.js";
import { renderMemories } from "./memories.js";
import { renderChart } from "./charts.js";

document.addEventListener("DOMContentLoaded", () => {
  let state = loadState();
  let selectedPlant = null;
  let selectedPerson = "me";

  const $ = selector => document.querySelector(selector);
  const $$ = selector => document.querySelectorAll(selector);

  function persist() {
    saveState(state);
  }

  function notify(message) {
    const toast = $("#toast");

    if (!toast) {
      alert(message);
      return;
    }

    toast.textContent = message;
    toast.classList.add("show");

    setTimeout(() => {
      toast.classList.remove("show");
    }, 2500);
  }

  function refresh() {
    renderGarden(state);
    renderShop(state);
    renderAchievements(state);
    renderMemories(state);
    renderChart(state);

    $("#event-count").textContent = state.events.length;

    renderEvents();
    renderAnniversary();
  }

  // Fecha de hoy
  $("#today-label").textContent =
    new Date().toLocaleDateString("es-ES", {
      weekday: "long",
      day: "numeric",
      month: "long"
    });

  // Cuidar el jardín
  $("#care-today")?.addEventListener("click", () => {
    const result = careTogether(state);

    if (result.ok) {
      persist();
      refresh();
    }

    notify(result.message);
  });

  // Abrir selector de plantas
  $$(".edit-plant").forEach(button => {
    button.addEventListener("click", () => {
      selectedPerson = button.dataset.person;
      selectedPlant = state.people[selectedPerson].plantId;

      $("#plant-dialog-title").textContent =
        selectedPerson === "me"
          ? "Elige tu planta, Laura"
          : "Elige tu planta, Pablo";

      renderPlantChoices();
      $("#plant-dialog").showModal();
    });
  });

  // Mostrar opciones de plantas
  function renderPlantChoices() {
    const host = $("#plant-choices");
    host.replaceChildren();

    PLANTS.forEach(plant => {
      const purchased = state.inventory.some(
        item => item.id === plant.id
      );

      const available = plant.price === 0 || purchased;

      const button = document.createElement("button");
      button.type = "button";
      button.className = "plant-choice";

      if (selectedPlant === plant.id) {
        button.classList.add("selected");
      }

      button.disabled = !available;

      const emoji = document.createElement("span");
      emoji.textContent = plant.emoji;

      const name = document.createElement("b");
      name.textContent = plant.name;

      const description = document.createElement("small");
      description.textContent = available
        ? plant.description
        : `${plant.price} monedas · Bloqueada`;

      button.append(emoji, name, description);

      button.addEventListener("click", () => {
        selectedPlant = plant.id;
        renderPlantChoices();
      });

      host.append(button);
    });
  }

  // Guardar planta elegida
  $("#save-plant")?.addEventListener("click", () => {
    if (!selectedPlant) {
      notify("Selecciona una planta.");
      return;
    }

    const plant = PLANTS.find(item => item.id === selectedPlant);

    const purchased = state.inventory.some(
      item => item.id === selectedPlant
    );

    if (plant.price > 0 && !purchased) {
      notify("Primero tienes que comprar esta planta.");
      return;
    }

    state.people[selectedPerson].plantId = selectedPlant;

    persist();
    refresh();

    $("#plant-dialog").close();
    notify("¡Planta cambiada! 🌱");
  });

  // Registrar ingresos y gastos
  $("#event-form")?.addEventListener("submit", event => {
    event.preventDefault();

    const form = event.currentTarget;
    const data = new FormData(form);

    const title = String(data.get("title") || "").trim();
    const amount = Number(data.get("amount"));
    const date = data.get("date");
    const person = data.get("person");
    const type = data.get("type");

    if (!title || !date || !Number.isFinite(amount) || amount <= 0) {
      notify("Comprueba los datos del movimiento.");
      return;
    }

    if (!["me", "partner"].includes(person)) {
      notify("Selecciona una persona válida.");
      return;
    }

    if (!["income", "expense"].includes(type)) {
      notify("Selecciona un movimiento válido.");
      return;
    }

    if (
      type === "expense" &&
      amount > state.people[person].balance
    ) {
      notify("No tienes suficientes FamilyCoins.");
      return;
    }

    if (type === "income") {
      state.people[person].balance += amount;
    } else {
      state.people[person].balance -= amount;
    }

    state.events.push({
      id: crypto.randomUUID
        ? crypto.randomUUID()
        : String(Date.now()),
      title,
      amount,
      date,
      person,
      type
    });

    persist();
    form.reset();

    form.elements.date.value = dateISO();

    refresh();
    notify("¡Movimiento guardado!");
  });

  // Mostrar movimientos
  function renderEvents() {
    const host = $("#event-list");
    host.replaceChildren();

    const search = $("#search-events").value.toLowerCase();
    const personFilter = $("#filter-person").value;
    const typeFilter = $("#filter-type").value;

    const events = [...state.events]
      .sort((a, b) => b.date.localeCompare(a.date))
      .filter(item => {
        const matchesSearch =
          item.title.toLowerCase().includes(search);

        const matchesPerson =
          personFilter === "all" ||
          item.person === personFilter;

        const matchesType =
          typeFilter === "all" ||
          item.type === typeFilter;

        return matchesSearch && matchesPerson && matchesType;
      });

    $("#events-empty").hidden = events.length > 0;

    events.forEach(item => {
      const row = document.createElement("article");
      row.className = "event-item";

      const title = document.createElement("b");
      title.textContent = item.title;

      const person = document.createElement("small");
      person.textContent =
        state.people[item.person].name +
        " · " +
        new Date(item.date + "T12:00:00").toLocaleDateString("es-ES");

      const amount = document.createElement("strong");
      amount.textContent =
        `${item.type === "income" ? "+" : "−"}${item.amount} Coins`;

      amount.className = item.type;

      row.append(title, person, amount);
      host.append(row);
    });
  }

  // Filtros de movimientos
  $("#search-events")?.addEventListener("input", renderEvents);
  $("#filter-person")?.addEventListener("change", renderEvents);
  $("#filter-type")?.addEventListener("change", renderEvents);

  $("#reset-filters")?.addEventListener("click", () => {
    $("#search-events").value = "";
    $("#filter-person").value = "all";
    $("#filter-type").value = "all";

    renderEvents();
  });

  // Comprar artículos
  $("#shop-grid")?.addEventListener("click", event => {
    const button = event.target.closest("[data-buy-item]");

    if (!button) return;

    const result = buyItem(state, button.dataset.buyItem);

    if (!result.ok) {
      notify(result.message);
      return;
    }

    persist();
    refresh();
    notify(result.message);
  });

  // Abrir configuración de aniversario
  $("#edit-anniversary")?.addEventListener("click", () => {
    const form = $("#anniversary-form");

    form.elements.title.value = state.anniversary.title || "";
    form.elements.date.value = state.anniversary.date || "";

    $("#anniversary-dialog").showModal();
  });

  // Guardar aniversario
  $("#anniversary-form")?.addEventListener("submit", event => {
    event.preventDefault();

    const data = new FormData(event.currentTarget);

    state.anniversary = {
      title: String(data.get("title")).trim(),
      date: data.get("date")
    };

    persist();
    refresh();

    $("#anniversary-dialog").close();
    notify("¡Fecha especial guardada! ❤️");
  });

  // Mostrar aniversario
  function renderAnniversary() {
    const anniversary = state.anniversary;

    if (!anniversary.date) {
      $("#anniversary-count").textContent = "—";
      $("#anniversary-caption").textContent = "Preparad algo bonito";
      $("#anniversary-message").textContent =
        "Guardad vuestra fecha especial";
      return;
    }

    const today = new Date();
    const [, month, day] = anniversary.date.split("-").map(Number);

    let next = new Date(today.getFullYear(), month - 1, day);

    const todayStart = new Date(
      today.getFullYear(),
      today.getMonth(),
      today.getDate()
    );

    if (next < todayStart) {
      next.setFullYear(today.getFullYear() + 1);
    }

    const remaining = Math.round(
      (next - todayStart) / 86400000
    );

    $("#anniversary-count").textContent =
      remaining === 0 ? "¡Hoy!" : `${remaining} días`;

    $("#anniversary-caption").textContent = anniversary.title;
    $("#anniversary-message").textContent = anniversary.title;
  }

  // Guardar recuerdos
  $("#memory-form")?.addEventListener("submit", event => {
    event.preventDefault();

    const data = new FormData(event.currentTarget);

    const title = String(data.get("title") || "").trim();
    const date = data.get("date");
    const note = String(data.get("note") || "").trim();

    if (!title || !date || !note) {
      notify("Completa todos los campos.");
      return;
    }

    state.memories.push({
      id: crypto.randomUUID
        ? crypto.randomUUID()
        : String(Date.now()),
      title,
      date,
      note
    });

    persist();
    event.currentTarget.reset();
    refresh();

    notify("¡Recuerdo guardado! 💚");
  });

  // Cambiar tema
  $("#theme-toggle")?.addEventListener("click", () => {
    document.body.classList.toggle("dark-mode");

    state.theme = document.body.classList.contains("dark-mode")
      ? "dark"
      : "light";

    persist();
  });

  if (state.theme === "dark") {
    document.body.classList.add("dark-mode");
  }

  // Cambiar periodo de la gráfica
  $("#chart-period")?.addEventListener("change", () => {
    renderChart(state);
  });

  // Fecha inicial del formulario
  $("#event-form").elements.date.value = dateISO();

  // Iniciar aplicación
  refresh();

  console.log("FamilyCoins iniciado correctamente 🌱");
});
