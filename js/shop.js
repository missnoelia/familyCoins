
import { PLANTS } from "./plants.js";
import { saveState } from "./storage.js";

export const SHOP_ITEMS = [
  ...PLANTS
    .filter(plant => plant.price > 0)
    .map(plant => ({
      id: plant.id,
      name: plant.name,
      emoji: plant.emoji,
      price: plant.price,
      description: plant.description,
      type: "plant"
    })),
  {
    id: "flower-pot",
    name: "Maceta bonita",
    emoji: "🪴",
    price: 20,
    description: "Una maceta para decorar vuestro jardín",
    type: "decoration"
  },
  {
    id: "butterfly",
    name: "Mariposa",
    emoji: "🦋",
    price: 15,
    description: "Una visitante para vuestro jardín",
    type: "decoration"
  },
  {
    id: "rainbow",
    name: "Arcoíris",
    emoji: "🌈",
    price: 30,
    description: "Un toque de color",
    type: "decoration"
  },
  {
    id: "fountain",
    name: "Fuente",
    emoji: "⛲",
    price: 50,
    description: "Una fuente para vuestro rincón verde",
    type: "decoration"
  }
];

export function renderShop(state) {
  const host = document.querySelector("#shop-grid");
  if (!host) return;

  host.replaceChildren();

  SHOP_ITEMS.forEach(item => {
    const owned = state.inventory.some(entry => entry.id === item.id);

    const card = document.createElement("article");
    card.className = "shop-item";

    const emoji = document.createElement("div");
    emoji.className = "shop-item-emoji";
    emoji.textContent = item.emoji;

    const title = document.createElement("h3");
    title.textContent = item.name;

    const description = document.createElement("p");
    description.textContent = item.description;

    const price = document.createElement("p");
    price.textContent = `${item.price} monedas`;

    const button = document.createElement("button");
    button.type = "button";
    button.dataset.buyItem = item.id;
    button.textContent = owned ? "Comprado" : "Comprar";
    button.disabled = owned;

    card.append(emoji, title, description, price, button);
    host.append(card);
  });
}

export function buyItem(state, itemId) {
  const item = SHOP_ITEMS.find(item => item.id === itemId);

  if (!item) {
    return { ok: false, message: "No se ha encontrado el artículo." };
  }

  if (state.inventory.some(entry => entry.id === itemId)) {
    return { ok: false, message: "Ya tenéis este artículo." };
  }

  const total =
    state.people.me.balance + state.people.partner.balance;

  if (total < item.price) {
    return { ok: false, message: "No tenéis suficientes monedas." };
  }

  const fromMe = Math.min(state.people.me.balance, item.price);
  state.people.me.balance -= fromMe;

  const remaining = item.price - fromMe;

  if (remaining > 0) {
    state.people.partner.balance -= remaining;
  }

  state.inventory.push({
    id: item.id,
    name: item.name,
    type: item.type,
    date: new Date().toISOString().slice(0, 10)
  });

  saveState(state);

  return {
    ok: true,
    message: `¡Habéis comprado ${item.name}!`
  };
}
