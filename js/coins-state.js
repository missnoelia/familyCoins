
/**
 * Estado global de la aplicación
 */

class CoinsState {

    constructor() {

        // Usuario que ha iniciado sesión
        this.currentUser = null;

        // Pareja vinculada
        this.partner = null;

        // Saldos de los dos usuarios
        this.balances = {
            currentUser: 0,
            partner: 0
        };

        // Eventos registrados
        this.events = [];

        // Eventos predefinidos
        this.predefinedEvents = [];

        // Filtros de movimientos
        this.filters = {
            person: "all",
            effect: "all",
            startDate: "",
            endDate: "",
            month: "",
            search: "",
            annulled: "no"
        };

        // Periodo de las gráficas
        this.chartGranularity = "month";

        // Estado de sesión
        this.isLoggedIn = false;

        // Indica si el usuario tiene pareja
        this.hasPartner = false;

        // Indica si el usuario es superadmin
        this.isSuperAdmin = false;
    }

    // Guardar usuario actual
    setUser(user) {

        this.currentUser = user;
        this.isLoggedIn = Boolean(user);

        this.isSuperAdmin = user?.rol === "superadmin";
    }

    // Guardar pareja
    setPartner(partner) {

        this.partner = partner;
        this.hasPartner = Boolean(partner);
    }

    // Actualizar saldos
    setBalances(currentUserBalance, partnerBalance) {

        this.balances.currentUser = Number(currentUserBalance) || 0;
        this.balances.partner = Number(partnerBalance) || 0;
    }

    // Guardar eventos
    setEvents(events) {

        this.events = Array.isArray(events) ? events : [];
    }

    // Guardar eventos predefinidos
    setPredefinedEvents(events) {

        this.predefinedEvents = Array.isArray(events) ? events : [];
    }

    // Actualizar filtros
    setFilters(newFilters) {

        this.filters = {
            ...this.filters,
            ...newFilters
        };
    }

    // Cambiar periodo de las gráficas
    setChartGranularity(granularity) {

        const validGranularities = [
            "month",
            "quarter",
            "year"
        ];

        if (validGranularities.includes(granularity)) {
            this.chartGranularity = granularity;
        }
    }

    // Comprobar si hay sesión iniciada
    hasSession() {

        return this.isLoggedIn;
    }

    // Comprobar si es superadmin
    isAdmin() {

        return this.isSuperAdmin;
    }

    // Limpiar información de sesión
    clearSession() {

        this.currentUser = null;
        this.partner = null;

        this.balances = {
            currentUser: 0,
            partner: 0
        };

        this.events = [];
        this.predefinedEvents = [];

        this.isLoggedIn = false;
        this.hasPartner = false;
        this.isSuperAdmin = false;
    }
}

// Crear una única instancia global
window.coinsState = new CoinsState();
