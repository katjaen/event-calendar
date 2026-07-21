/**
 * Testy logiki dat z gutenberg-event-sidebar.js (toggleAllDay,
 * validateAndFormatDates) — czyste funkcje wywoływane z handlerów
 * onChange w sidebarze Gutenberga.
 *
 * Plik referencuje globalne `wp.*` (Gutenberg) bezwarunkowo na najwyższym
 * poziomie (registerPlugin(...) na końcu pliku), więc trzeba je podstawić
 * PRZED require() — inaczej require rzuci wyjątkiem od razu.
 */

function mockWpGlobal() {
	global.wp = {
		plugins: { registerPlugin: jest.fn() },
		editor: { PluginDocumentSettingPanel: () => null },
		components: {
			CheckboxControl: () => null,
			TextControl: () => null,
			ColorPalette: () => null,
		},
		data: {
			useSelect: jest.fn(),
			useDispatch: jest.fn(() => ({ editPost: jest.fn() })),
		},
		element: {
			createElement: jest.fn(),
			Fragment: "Fragment",
			useState: jest.fn(() => [null, jest.fn()]),
		},
	};
}

function loadSidebarFresh() {
	jest.resetModules();
	mockWpGlobal();
	return require("../assets/js/gutenberg-event-sidebar.js");
}

describe("toggleAllDay()", () => {
	test("włączenie all-day przycina start/end do samej daty (bez godziny)", () => {
		const { toggleAllDay } = loadSidebarFresh();
		const editPost = jest.fn();

		toggleAllDay(true, {}, "2026-08-10T09:00", "2026-08-10T17:00", editPost);

		expect(editPost).toHaveBeenCalledWith({
			meta: {
				_event_all_day: "1",
				_event_start: "2026-08-10",
				_event_end: "2026-08-10",
			},
		});
	});

	test("wyłączenie all-day dokleja domyślne godziny 00:00 / 23:59 do samych dat", () => {
		const { toggleAllDay } = loadSidebarFresh();
		const editPost = jest.fn();

		toggleAllDay(false, {}, "2026-08-10", "2026-08-11", editPost);

		expect(editPost).toHaveBeenCalledWith({
			meta: {
				_event_all_day: "0",
				_event_start: "2026-08-10T00:00",
				_event_end: "2026-08-11T23:59",
			},
		});
	});

	test("wyłączenie all-day nie dubluje godziny, jeśli już jest obecna", () => {
		const { toggleAllDay } = loadSidebarFresh();
		const editPost = jest.fn();

		toggleAllDay(
			false,
			{},
			"2026-08-10T09:30",
			"2026-08-10T18:00",
			editPost,
		);

		expect(editPost).toHaveBeenCalledWith({
			meta: {
				_event_all_day: "0",
				_event_start: "2026-08-10T09:30",
				_event_end: "2026-08-10T18:00",
			},
		});
	});

	test("zachowuje resztę meta niezmienioną (spread ...meta)", () => {
		const { toggleAllDay } = loadSidebarFresh();
		const editPost = jest.fn();

		toggleAllDay(
			true,
			{ _event_location: "Sala A", _is_event: "1" },
			"2026-08-10T09:00",
			"",
			editPost,
		);

		expect(editPost).toHaveBeenCalledWith({
			meta: expect.objectContaining({
				_event_location: "Sala A",
				_is_event: "1",
			}),
		});
	});
});

describe("validateAndFormatDates()", () => {
	test("data-godzina bez zmian, gdy end >= start", () => {
		const { validateAndFormatDates } = loadSidebarFresh();
		const setWarning = jest.fn();

		const result = validateAndFormatDates(
			"end",
			"2026-08-10T17:00",
			"2026-08-10T09:00",
			"",
			setWarning,
		);

		expect(result).toEqual({
			newStart: "2026-08-10T09:00",
			newEnd: "2026-08-10T17:00",
		});
		expect(setWarning).not.toHaveBeenCalled();
	});

	test("data bez godziny dostaje doklejone T00:00 (potrzebne do porównania end>=start)", () => {
		const { validateAndFormatDates } = loadSidebarFresh();
		const result = validateAndFormatDates(
			"start",
			"2026-08-10",
			"2026-08-10",
			"2026-08-11",
			jest.fn(),
		);
		expect(result.newStart).toBe("2026-08-10T00:00");
	});

	test("end wcześniejszy niż start: przycina end do start i woła setWarning z etykietą PHP", () => {
		global.eventCalendarSidebarLabels = {
			endBeforeStartWarning: "End date must be after start date",
		};
		const { validateAndFormatDates } = loadSidebarFresh();
		const setWarning = jest.fn();

		const result = validateAndFormatDates(
			"end",
			"2026-08-09T08:00", // wcześniej niż start
			"2026-08-10T09:00",
			"",
			setWarning,
		);

		expect(result.newEnd).toBe(result.newStart);
		expect(setWarning).toHaveBeenCalledWith(
			"End date must be after start date",
		);
	});

	test("end wcześniejszy niż start, ale brak etykiety PHP -> nadal przycina, nie woła setWarning", () => {
		delete global.eventCalendarSidebarLabels;
		const { validateAndFormatDates } = loadSidebarFresh();
		const setWarning = jest.fn();

		const result = validateAndFormatDates(
			"end",
			"2026-08-09T08:00",
			"2026-08-10T09:00",
			"",
			setWarning,
		);

		expect(result.newEnd).toBe(result.newStart);
		expect(setWarning).not.toHaveBeenCalled();
	});

	test("brak jednej z dat (dopiero pierwsze pole wypełniane) -> brak porównania, brak warninga", () => {
		const { validateAndFormatDates } = loadSidebarFresh();
		const setWarning = jest.fn();

		const result = validateAndFormatDates(
			"start",
			"2026-08-10T09:00",
			"",
			"",
			setWarning,
		);

		expect(result.newStart).toBe("2026-08-10T09:00");
		expect(result.newEnd).toBe("");
		expect(setWarning).not.toHaveBeenCalled();
	});
});
