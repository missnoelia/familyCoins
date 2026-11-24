
import { getGrowth, getPlant } from "./plants.js";
import { dateISO, daysBetween } from "./storage.js";

export function careForPerson(state, personId, today = dateISO()) {
  const person = state.people[personId];

  if (person.lastCareDate === today) {
    return {
      ok: false,
      message: `${person.name} ya ha cuidado su planta hoy.`
    };
  }

  if (
    person.lastCareDate &&
    daysBetween(person.lastCareDate, today) === 1
  ) {
    person.streak += 1;
  } else {
    person.streak = 1;
  }

  person.lastCareDate = today;

  if (!person.careDates.includes(today)) {
    person.careDates.push(today);
  }

  return {
    ok: true,
    message: `¡${person.name} ha cuidado su planta! 🌱`
  };
}

export function careTogether(state, today = dateISO()) {
  const first = careForPerson(state, "me", today);
  const second = careForPerson(state, "partner", today);

  if (first.ok && second.ok) {
    if (!state.sharedCareDates.includes(today)) {
      state.sharedCareDates.push(today);
    }

    return {
      ok: true,
      message: "¡Habéis cuidado el jardín juntos! 🌿"
    };
  }

  return {
    ok: false,
    message: first.message || second.message
  };
}

export function sharedStreak(state) {
  const dates = [...state.sharedCareDates].sort();

  if (!dates.length) return 0;

  let streak = 1;

  for (let i = dates.length - 1; i > 0; i--) {
    if (daysBetween(dates[i - 1], dates[i]) === 1) {
      streak++;
    } else {
      break;
    }
  }

  return streak;
}

export function renderGarden(state) {
  ["me", "partner"].forEach(id => {
    const person = state.people[id];
    const plant = getPlant(person.plantId);
    const growth = getGrowth(person.streak);

    const setText = (selector, value) => {
      const element = document.querySelector(selector);
      if (element) element.textContent = value;
    };

    const plantEl = document.querySelector(`#plant-${id}`);

    if (plantEl) {
      plantEl.textContent = plant.emoji;
      plantEl.classList.toggle("grown", growth.level >= 3);
    }

    setText(`#name-${id}`, person.name);
    setText(`#species-${id}`, plant.name);
    setText(`#level-${id}`, `Nivel ${growth.level} · ${growth.stage}`);
    setText(
      `#streak-${id}`,
      `${person.streak} ${person.streak === 1 ? "día" : "días"}`
    );
    setText(`#progress-label-${id}`,
      growth.next
        ? `Faltan ${growth.next} días para el siguiente nivel`
        : "¡Tu planta ha alcanzado su máximo nivel!"
    );
    setText(`#balance-${id}`, person.balance.toLocaleString("es-ES"));

    const progress = document.querySelector(`#progress-${id}`);

    if (progress) {
      progress.style.width =
        `${Math.max(0, Math.min(100, growth.progress))}%`;
    }
  });

  const total =
    state.people.me.balance + state.people.partner.balance;

  const setText = (selector, value) => {
    const element = document.querySelector(selector);
    if (element) element.textContent = value;
  };

  setText("#total-balance", total.toLocaleString("es-ES"));
  setText("#shop-wallet", total.toLocaleString("es-ES"));
  setText("#shared-streak", `${sharedStreak(state)} días cuidando el jardín`);
  setText("#shared-count", `${sharedStreak(state)} días`);
}
