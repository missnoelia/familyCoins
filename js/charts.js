
let chartInstance = null;

export function renderChart(state) {
  const canvas = document.querySelector("#activity-chart");

  if (!canvas || !window.Chart) return;

  const period =
    document.querySelector("#chart-period")?.value || "month";

  const now = new Date();

  const events = state.events.filter(event => {
    if (period === "all") return true;

    const date = new Date(`${event.date}T12:00:00`);

    return (
      date.getMonth() === now.getMonth() &&
      date.getFullYear() === now.getFullYear()
    );
  });

  const totals = {
    me: { income: 0, expense: 0 },
    partner: { income: 0, expense: 0 }
  };

  events.forEach(event => {
    if (
      totals[event.person] &&
      ["income", "expense"].includes(event.type)
    ) {
      totals[event.person][event.type] += Number(event.amount);
    }
  });

  if (chartInstance) {
    chartInstance.destroy();
  }

  chartInstance = new Chart(canvas, {
    type: "bar",
    data: {
      labels: [
        state.people.me.name,
        state.people.partner.name
      ],
      datasets: [
        {
          label: "Ingresos",
          data: [
            totals.me.income,
            totals.partner.income
          ],
          backgroundColor: "#75b99b",
          borderRadius: 7
        },
        {
          label: "Gastos",
          data: [
            totals.me.expense,
            totals.partner.expense
          ],
          backgroundColor: "#e98b7d",
          borderRadius: 7
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "bottom",
          labels: {
            usePointStyle: true,
            boxWidth: 7,
            font: {
              family: "DM Sans",
              size: 10
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false }
        },
        y: {
          beginAtZero: true,
          grid: { color: "#78877e22" }
        }
      }
    }
  });
}
