
const DEFINITIONS = [
  {
    id: "first-care",
    title: "Primer cuidado",
    detail: "Cuidar una planta por primera vez",
    emoji: "🌱",
    test: state =>
      state.people.me.careDates.length +
      state.people.partner.careDates.length >= 1
  },
  {
    id: "week",
    title: "Una semana creciendo",
    detail: "Conseguir una racha de 7 días",
    emoji: "🌼",
    test: state =>
      Math.max(
        state.people.me.streak,
        state.people.partner.streak
      ) >= 7
  },
  {
    id: "together",
    title: "En equipo",
    detail: "Cuidar el jardín juntos",
    emoji: "💕",
    test: state => state.sharedCareDates.length >= 1
  },
  {
    id: "month",
    title: "Un mes de constancia",
    detail: "Alcanzar 30 días de racha",
    emoji: "🏵️",
    test: state =>
      Math.max(
        state.people.me.streak,
        state.people.partner.streak
      ) >= 30
  },
  {
    id: "collector",
    title: "Pequeños coleccionistas",
    detail: "Comprar 3 elementos en la tienda",
    emoji: "🪴",
    test: state => state.inventory.length >= 3
  }
];

export function renderAchievements(state) {
  const host = document.querySelector("#achievement-list");
  if (!host) return;

  host.replaceChildren();

  DEFINITIONS.forEach(item => {
    const unlocked = item.test(state);

    const row = document.createElement("div");
    row.className = `achievement ${unlocked ? "" : "locked"}`;

    const icon = document.createElement("span");
    icon.className = "achievement-icon";
    icon.textContent = unlocked ? item.emoji : "🔒";

    const text = document.createElement("div");

    const title = document.createElement("b");
    title.textContent = item.title;

    const detail = document.createElement("small");
    detail.textContent = unlocked
      ? "¡Logro desbloqueado!"
      : item.detail;

    text.append(title, detail);
    row.append(icon, text);
    host.append(row);
  });
}
