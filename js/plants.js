
export const PLANTS = [
  {
    id: "tulip",
    name: "Tulipán",
    emoji: "🌷",
    price: 0,
    description: "Alegre y delicado"
  },
  {
    id: "sunflower",
    name: "Girasol",
    emoji: "🌻",
    price: 35,
    description: "Siempre busca la luz"
  },
  {
    id: "monstera",
    name: "Monstera",
    emoji: "🪴",
    price: 0,
    description: "Verde y resistente"
  },
  {
    id: "cactus",
    name: "Cactus",
    emoji: "🌵",
    price: 25,
    description: "Pequeño pero fuerte"
  },
  {
    id: "rose",
    name: "Rosal",
    emoji: "🌹",
    price: 45,
    description: "Un clásico del jardín"
  },
  {
    id: "cherry",
    name: "Cerezo",
    emoji: "🌸",
    price: 60,
    description: "Florece en primavera"
  },
  {
    id: "seedling",
    name: "Brote",
    emoji: "🌱",
    price: 0,
    description: "Todo empieza aquí"
  }
];

export function getPlant(id) {
  return PLANTS.find(plant => plant.id === id) || PLANTS[0];
}

export function getGrowth(streak) {
  if (streak >= 30) {
    return {
      level: 5,
      stage: "Florecida",
      progress: 100,
      next: 0,
      multiplier: 1.35
    };
  }

  if (streak >= 14) {
    return {
      level: 4,
      stage: "Adulta",
      progress: ((streak - 14) / 16) * 100,
      next: 30 - streak,
      multiplier: 1.2
    };
  }

  if (streak >= 7) {
    return {
      level: 3,
      stage: "Joven",
      progress: ((streak - 7) / 7) * 100,
      next: 14 - streak,
      multiplier: 1.1
    };
  }

  if (streak >= 3) {
    return {
      level: 2,
      stage: "Brote",
      progress: ((streak - 3) / 4) * 100,
      next: 7 - streak,
      multiplier: 1.05
    };
  }

  return {
    level: 1,
    stage: "Semilla",
    progress: (streak / 3) * 100,
    next: 3 - streak,
    multiplier: 1
  };
}
