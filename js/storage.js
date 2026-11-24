
const STORAGE_KEY = "familycoins-garden-demo-v1";

export const dateISO = (date = new Date()) => {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 10);
};

export const daysBetween = (a, b) => {
  const [ay, am, ad] = a.split("-").map(Number);
  const [by, bm, bd] = b.split("-").map(Number);

  return Math.round(
    (Date.UTC(by, bm - 1, bd) - Date.UTC(ay, am - 1, ad)) / 86400000
  );
};

export function createInitialState() {
  return {
    version: 1,
    people: {
      me: {
        name: "Laura",
        balance: 420,
        plantId: "tulip",
        streak: 0,
        lastCareDate: null,
        careDates: []
      },
      partner: {
        name: "Pablo",
        balance: 310,
        plantId: "monstera",
        streak: 0,
        lastCareDate: null,
        careDates: []
      }
    },
    events: [
      {
        id: "demo-1",
        title: "Cena especial",
        amount: 40,
        type: "income",
        person: "me",
        date: dateISO()
      },
      {
        id: "demo-2",
        title: "Escapada de fin de semana",
        amount: 30,
        type: "expense",
        person: "partner",
        date: dateISO()
      }
    ],
    anniversary: {
      title: "Nuestro aniversario",
      date: ""
    },
    memories: [],
    inventory: [],
    sharedCareDates: [],
    theme: "light"
  };
}

export function loadState() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);

    if (!raw) {
      const initial = createInitialState();
      saveState(initial);
      return initial;
    }

    const parsed = JSON.parse(raw);

    if (
      !parsed ||
      parsed.version !== 1 ||
      !parsed.people ||
      !parsed.events
    ) {
      const initial = createInitialState();
      saveState(initial);
      return initial;
    }

    return parsed;
  } catch (error) {
    console.warn("Error leyendo los datos:", error);
    return createInitialState();
  }
}

export function saveState(state) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

export function resetDemoState() {
  localStorage.removeItem(STORAGE_KEY);
  return createInitialState();
}
