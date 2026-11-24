
export function renderMemories(state) {
  const host = document.querySelector("#memory-list");
  if (!host) return;

  host.replaceChildren();

  if (!state.memories.length) {
    const empty = document.createElement("p");
    empty.className = "muted";
    empty.textContent =
      "Todavía no hay recuerdos. ¡Guardad el primero!";

    host.append(empty);
    return;
  }

  [...state.memories]
    .sort((a, b) => b.date.localeCompare(a.date))
    .forEach(memory => {
      const card = document.createElement("article");
      card.className = "memory-card";

      const title = document.createElement("b");
      title.textContent = memory.title;

      const date = document.createElement("small");
      date.textContent = new Date(
        `${memory.date}T12:00:00`
      ).toLocaleDateString("es-ES");

      const note = document.createElement("p");
      note.textContent = memory.note;

      card.append(title, date, note);
      host.append(card);
    });
}
