/**
 * Testy czystych funkcji z calendar-init.js (mapowanie eventów, formatowanie
 * czasu, walidacja widoku, wykrywanie pierwszego dnia tygodnia).
 *
 * calendar-init.js to zwykły skrypt (nie moduł ES) ładowany w przeglądarce
 * przez wp_enqueue_script — require() go tutaj wykonuje cały top-level kodu
 * (rejestruje tylko listener na DOMContentLoaded, który się NIE odpala
 * automatycznie w jsdom, bo to zdarzenie zaszło już przy starcie środowiska
 * testowego, zanim require() w ogóle się wykonał).
 */

function loadCalendarInitFresh() {
	jest.resetModules();
	return require("../assets/js/calendar-init.js");
}

describe("normalizeView()", () => {
	const { normalizeView } = loadCalendarInitFresh();

	test.each([
		["day", "day"],
		["week", "week"],
		["month", "month"],
	])("widok poprawny %s zostaje bez zmian", (input, expected) => {
		expect(normalizeView(input)).toBe(expected);
	});

	test.each([undefined, null, "", "rok", "agenda"])(
		"widok niepoprawny (%p) -> fallback 'month'",
		input => {
			expect(normalizeView(input)).toBe("month");
		},
	);
});

describe("getStartDayOfWeek()", () => {
	const { getStartDayOfWeek } = loadCalendarInitFresh();

	test("brak calendarData -> fallback 1 (poniedziałek)", () => {
		expect(getStartDayOfWeek(null)).toBe(1);
	});

	test("startDaySource='wp' -> bierze wprost startOfWeek z ustawień WP", () => {
		expect(
			getStartDayOfWeek({ startDaySource: "wp", startOfWeek: "0" }),
		).toBe(0);
		expect(
			getStartDayOfWeek({ startDaySource: "wp", startOfWeek: "1" }),
		).toBe(1);
	});

	test("startDaySource='locale', en-US -> niedziela (0)", () => {
		expect(
			getStartDayOfWeek({ startDaySource: "locale", locale: "en-US" }),
		).toBe(0);
	});

	test("startDaySource='locale', pl-PL -> poniedziałek (1), poza listą 'sundayStart'", () => {
		expect(
			getStartDayOfWeek({ startDaySource: "locale", locale: "pl-PL" }),
		).toBe(1);
	});
});

describe("mapEventToToast()", () => {
	const { mapEventToToast } = loadCalendarInitFresh();

	test("event bez 'start' jest odrzucany (null)", () => {
		expect(mapEventToToast({ id: 1, title: "Bez daty" })).toBeNull();
	});

	test("event z niepoprawną datą start jest odrzucany (null)", () => {
		expect(
			mapEventToToast({ id: 1, start: "nie-jest-data", title: "X" }),
		).toBeNull();
	});

	test("poprawny event czasowy mapuje się na format ToastUI", () => {
		const mapped = mapEventToToast({
			id: 42,
			title: "Warsztaty",
			start: "2026-08-10T10:00:00",
			end: "2026-08-10T12:00:00",
			allDay: false,
			location: "Sala A",
			description: "Opis",
			backgroundColor: "#ff0000",
			borderColor: "#990000",
			url: "https://example.test/warsztaty",
		});

		expect(mapped).toMatchObject({
			id: "42",
			calendarId: "1",
			title: "Warsztaty",
			category: "time",
			isAllDay: false,
			location: "Sala A",
			body: "Opis",
			backgroundColor: "#ff0000",
			borderColor: "#990000",
			raw: { url: "https://example.test/warsztaty" },
		});
	});

	test("event całodniowy dostaje category='allday'", () => {
		const mapped = mapEventToToast({
			id: 1,
			start: "2026-08-10",
			allDay: true,
		});
		expect(mapped.category).toBe("allday");
		expect(mapped.isAllDay).toBe(true);
	});

	test("brak 'end' -> end = start (jednodniowy event)", () => {
		const mapped = mapEventToToast({ id: 1, start: "2026-08-10T09:00:00" });
		expect(mapped.end).toBe("2026-08-10T09:00:00");
	});

	test("brak koloru -> fallback do DEFAULT_EVENT_COLOR, nie do undefined", () => {
		const mapped = mapEventToToast({ id: 1, start: "2026-08-10T09:00:00" });
		expect(mapped.backgroundColor).toBeTruthy();
		expect(mapped.borderColor).toBe(mapped.backgroundColor);
	});
});

describe("formatTimeLabelCentral()", () => {
	beforeEach(() => {
		global.eventCalendarData = { locale: "en-US", use24Hour: false };
	});

	test("pusty string -> pusty label, bez wyjątku", () => {
		const { formatTimeLabelCentral } = loadCalendarInitFresh();
		expect(formatTimeLabelCentral("")).toBe("");
	});

	test("niepoprawna data -> pusty label, bez wyjątku", () => {
		const { formatTimeLabelCentral } = loadCalendarInitFresh();
		expect(formatTimeLabelCentral("zla-data")).toBe("");
	});

	test("use24Hour=true formatuje bez AM/PM", () => {
		global.eventCalendarData = { locale: "en-US", use24Hour: true };
		const { formatTimeLabelCentral } = loadCalendarInitFresh();
		const label = formatTimeLabelCentral("2026-08-10T14:30:00");
		expect(label).not.toMatch(/AM|PM/i);
	});

	test("use24Hour=false formatuje z AM/PM (en-US)", () => {
		global.eventCalendarData = { locale: "en-US", use24Hour: false };
		const { formatTimeLabelCentral } = loadCalendarInitFresh();
		const label = formatTimeLabelCentral("2026-08-10T14:30:00");
		expect(label).toMatch(/AM|PM/i);
	});
});
