/* =========================
    CSS HELPERS
========================= */
function getCSSHelpers() {
	let tempEl = null;

	return {
		getColor: varName => {
			if (!tempEl) {
				tempEl = document.createElement("div");
				tempEl.style.cssText = "position:absolute;visibility:hidden";
				document.body.appendChild(tempEl);
			}
			tempEl.style.color = `var(${varName})`;
			return getComputedStyle(tempEl).color || "rgb(0,0,0)";
		},

		getVar: varName => {
			return getComputedStyle(document.documentElement)
				.getPropertyValue(varName)
				.trim();
		},

		getNum: varName => {
			const value = getComputedStyle(document.documentElement)
				.getPropertyValue(varName)
				.trim();
			return parseFloat(value) || 0;
		},

		cleanup: () => {
			if (tempEl && tempEl.parentNode) {
				tempEl.parentNode.removeChild(tempEl);
				tempEl = null;
			}
		},
	};
}

/* =========================
    SETTINGS
========================= */
/*
 * START_DAY_OF_WEEK detection:
 * - Uses WordPress option 'start_of_week' if available
 * - Falls back to locale auto-detection
 *
 * IMPORTANT: dayNames must stay in natural order [Sun, Mon, ...]
 * TUI Calendar hardcodes weekend as indices 0 (Sun) and 6 (Sat)
 */
function getStartDayOfWeek(calendarData) {
	if (!calendarData) return 1; // fallback

	if (calendarData.startDaySource === "wp") {
		return parseInt(calendarData.startOfWeek, 10);
	}

	const locale = calendarData.locale || navigator.language;
	const sundayStartLocales = ["en-US", "en-CA", "ja", "he", "pt-BR"];
	return sundayStartLocales.some(l => locale.startsWith(l.split("-")[0]))
		? 0
		: 1;
}

// Will be set in DOMContentLoaded when eventCalendarData is available
// let START_DAY_OF_WEEK = 1;

let DEFAULT_EVENT_COLOR = "rgb(212, 196, 237)";

let CALENDAR_HOUR_START = 0;
let CALENDAR_HOUR_END = 24;

document.addEventListener("DOMContentLoaded", () => {
	if (typeof eventCalendarData === "undefined") {
		console.error("Event Calendar: Configuration not loaded");
		return;
	}

	if (typeof tui === "undefined" || !tui.Calendar) {
		console.error("Event Calendar: TUI Calendar library not loaded");
		return;
	}

	// ✅ Weź pierwszy kalendarz
	const firstCalendarData =
		Object.values(window.eventCalendarData)[0] || window.eventCalendarData;

	// ✅ Oblicz START_DAY_OF_WEEK
	START_DAY_OF_WEEK = getStartDayOfWeek(firstCalendarData);

	// DEBUG: Log configuration
	console.log("📅 Event Calendar Config:", {
		startOfWeekFromWP: firstCalendarData.startOfWeek,
		calculatedStartDay: START_DAY_OF_WEEK,
		dayNamesShort: firstCalendarData.dayNamesShort,
		locale: firstCalendarData.locale,
	});

	const helpers = getCSSHelpers();
	DEFAULT_EVENT_COLOR =
		helpers.getColor("--ec-event-color-default") || DEFAULT_EVENT_COLOR;

	const hStart = helpers.getNum("--ec-hour-start");
	CALENDAR_HOUR_START = Number.isFinite(hStart) ? hStart : CALENDAR_HOUR_START;

	const hEnd = helpers.getNum("--ec-hour-end");
	CALENDAR_HOUR_END = Number.isFinite(hEnd) ? hEnd : CALENDAR_HOUR_END;

	helpers.cleanup();

	initEventCalendars();
	initThemeObserver();
	observePopups();

	// --- Toggle for alt color scheme (ACSS) ---
	const toggle = document.querySelector("#color-scheme-toggle");
	if (toggle) {
		toggle.addEventListener("click", () => {
			document.documentElement.classList.toggle("color-scheme--alt");
			requestAnimationFrame(updateAllCalendarThemes);
		});
	}
});

/* =========================
	INIT
========================= */
function initEventCalendars() {
	const calendarElements = document.querySelectorAll(".ec-calendar");

	if (!calendarElements.length) {
		console.warn("Event Calendar: No calendar containers found");
		return;
	}

	calendarElements.forEach(el => createCalendar(el));
	initGlobalNavigation();
}

/* =========================
	CALENDAR CREATION
========================= */
function createCalendar(calendarEl) {
	try {
		// Destroy previous instance if exists
		if (calendarEl._tuiCalendar) {
			calendarEl._tuiCalendar.destroy();
			calendarEl._tuiCalendar = null;
		}

		const rawView = calendarEl.dataset.calendarView;
		const calendarView = normalizeView(rawView);
		const calendarId = calendarEl.dataset.calendarId;

		const calendar = new tui.Calendar(
			calendarEl,
			getCalendarConfig(calendarView, calendarId),
		);
		loadEvents(calendar, calendarId);
		calendarEl._tuiCalendar = calendar;
		scheduleCalendarRender(calendarId);
	} catch (error) {
		console.error(
			`Failed to create calendar ${calendarEl.dataset.calendarId}:`,
			error,
		);
	}
}

/* =========================
	CONFIG
========================= */
function getCalendarConfig(view, calendarId) {
	const calendarData =
		window.eventCalendarData[calendarId] || window.eventCalendarData;

	return {
		defaultView: view,
		theme: getActiveCalendarTheme(),
		useFormPopup: false,
		useDetailPopup: false,
		isReadOnly: true,
		usageStatistics: false,

		calendars: [
			{
				id: "1",
				name: "Events",
				backgroundColor: DEFAULT_EVENT_COLOR,
				borderColor: DEFAULT_EVENT_COLOR,
				dragBackgroundColor: DEFAULT_EVENT_COLOR,
			},
		],

		timezone: {
			zones: [
				{
					timezoneName: calendarData.timezone,
					displayLabel: "Local",
				},
			],
		},

		week: {
			startDayOfWeek: START_DAY_OF_WEEK,
			dayNames: calendarData.dayNamesShort,
			narrowWeekend: true,
			taskView: false,
			eventView: ["time", "allday"],
			alldayTitle: calendarData.allDayLabel,
			hourStart: CALENDAR_HOUR_START,
			hourEnd: CALENDAR_HOUR_END,
		},

		month: {
			startDayOfWeek: START_DAY_OF_WEEK,
			dayNames: calendarData.dayNamesShort,
			alldayTitle: calendarData.allDayLabel,
		},

		day: {
			eventView: ["time", "allday"],
			alldayTitle: calendarData.allDayLabel,
		},

		template: getCalendarTemplates(calendarData),
	};
}

/* =========================
    TEMPLATES
========================= */
function getCalendarTemplates(calendarData) {
	return {
		monthDayName(dayname) {
			const names = calendarData.dayNamesShort;
			return `<span class="calendar-week-dayname-name">${
				names[dayname.day] || ""
			}</span>`;
		},
		timegridDisplayPrimaryTime: ({ time }) =>
			formatTimeLabelCentral(time?.d?.d || time?.time?.d?.d),
		timegridDisplayTime: ({ time }) =>
			formatTimeLabelCentral(time?.d?.d || time?.time?.d?.d),
		alldayTitle: () => calendarData.allDayLabel,
		todayButtonText: calendarData.todayLabel,
	};
}

/* =========================
    TIME FORMAT
========================= */
function formatTimeLabelCentral(dateStr) {
	if (!dateStr) return "";

	try {
		const date = new Date(dateStr);

		if (isNaN(date.getTime())) {
			console.warn("Invalid date:", dateStr);
			return "";
		}

		const locale = eventCalendarData.locale || navigator.language;
		const options = {
			hour: "numeric",
			minute: "2-digit",
			hour12: !eventCalendarData.use24Hour,
		};

		return new Intl.DateTimeFormat(locale, options).format(date);
	} catch (error) {
		console.error("Error formatting time:", error);
		return "";
	}
}

/* =========================
	EVENTS LOADING
========================= */
async function loadEvents(calendar, calendarId) {
	try {
		const calendarData =
			window.eventCalendarData?.[calendarId] || window.eventCalendarData || {};

		const url = new URL(calendarData.apiUrl, window.location.origin);

		if (calendarData.queryId) {
			url.searchParams.set("query_id", calendarData.queryId);
		}

		const response = await fetch(url.toString());

		if (!response.ok) {
			throw new Error(`HTTP error! status: ${response.status}`);
		}

		const events = await response.json();

		if (!events?.length) {
			console.log(`No events found for calendar ${calendarId}`);
			return;
		}

		const mapped = events.map(mapEventToToast).filter(Boolean);

		if (mapped.length) {
			calendar.clear();
			calendar.createEvents(mapped);
			scheduleCalendarRender(calendarId);
		}
	} catch (error) {
		console.error(`Error loading events for calendar #${calendarId}`, error);
	}
}

/* =========================
	EVENT MAPPING
========================= */
function mapEventToToast(event) {
	try {
		if (!event?.start) return null;

		const startDate = new Date(event.start);
		const endDate = event.end ? new Date(event.end) : startDate;

		if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) return null;

		const isAllDay = !!event.allDay;
		const backgroundColor = event.backgroundColor || DEFAULT_EVENT_COLOR;
		const borderColor = event.borderColor || backgroundColor;

		return {
			id: String(event.id),
			calendarId: "1",
			title: event.title || "Event",
			category: isAllDay ? "allday" : "time",
			start: event.start,
			end: event.end || event.start,
			location: event.location || "",
			isAllDay: isAllDay,
			body: event.description || "",
			backgroundColor,
			borderColor,
		};
	} catch (error) {
		console.error("Error mapping event:", event?.id, error);
		return null;
	}
}

/* =========================
	GLOBAL NAVIGATION
========================= */
function initGlobalNavigation() {
	document.addEventListener("click", function (e) {
		const button = e.target.closest("[data-action]");
		if (!button) return;

		const navContainer = button.closest("[data-calendar-target]");
		const targetId = navContainer ? navContainer.dataset.calendarTarget : null;

		if (!targetId) return;

		const calendarEl = document.querySelector(
			`.ec-calendar[data-calendar-id="${targetId}"]`,
		);
		if (!calendarEl) return;

		const calendar = calendarEl._tuiCalendar;
		if (!calendar) return;

		switch (button.dataset.action) {
			case "prev":
				calendar.prev();
				break;
			case "next":
				calendar.next();
				break;
			case "today":
				calendar.today();
				break;
		}

		scheduleCalendarRender(targetId);
	});
}

/* =========================
    DEBOUNCED RENDER HOOK
========================= */
const calendarRenderDebounce = (() => {
	let timer;
	return calendarId => {
		clearTimeout(timer);
		timer = setTimeout(() => {
			onCalendarRendered(calendarId);
		}, 100);
	};
})();

/* =========================
    RENDER SCHEDULING
========================= */
function scheduleCalendarRender(calendarId) {
	calendarRenderDebounce(calendarId);
}

function onCalendarRendered(calendarId) {
	replaceMoreText();
	updateDateDisplayFromDOM(calendarId);
}

function replaceMoreText() {
	// Replace +N text
	const moreEls = document.querySelectorAll(
		".toastui-calendar-weekday-grid-more-events",
	);

	moreEls.forEach(el => {
		if (!el.dataset.eventCount) {
			const originalText = el.textContent.trim();
			const countMatch = originalText.match(/\d+/);
			if (!countMatch) return;
			el.dataset.eventCount = countMatch[0];
		}
		el.textContent = `+${el.dataset.eventCount}`;
	});

	// Replace day names in main calendar
	replaceDayNames();
}

/* =========================
	OTHER HELPERS
========================= */
function normalizeView(view) {
	return ["day", "week", "month"].includes(view) ? view : "month";
}

function updateDateDisplayFromDOM(calendarId) {
	const calendarEl = document.querySelector(
		`.ec-calendar[data-calendar-id="${calendarId}"]`,
	);
	if (!calendarEl || !calendarEl._tuiCalendar) return;

	const calendar = calendarEl._tuiCalendar;

	const dateEl = document.querySelector(
		`.ec-calendar-current-date[data-calendar-id="${calendarId}"]`,
	);
	if (!dateEl) return;

	const view = calendar.getViewName();
	const dateObj = calendar.getDate();
	const date = dateObj.toDate ? dateObj.toDate() : new Date(dateObj);
	const locale = eventCalendarData.locale || navigator.language;

	if (view === "day") {
		dateEl.textContent = date.toLocaleDateString(locale, {
			year: "numeric",
			month: "long",
			day: "numeric",
		});
	} else if (view === "week") {
		const start = new Date(date);
		const dayOffset = (start.getDay() - START_DAY_OF_WEEK + 7) % 7;
		start.setDate(start.getDate() - dayOffset);

		const end = new Date(start);
		end.setDate(start.getDate() + 6);

		const startStr = start.toLocaleDateString(locale, {
			day: "numeric",
			month: "short",
		});
		const endStr = end.toLocaleDateString(locale, {
			day: "numeric",
			month: "short",
			year: "numeric",
		});

		dateEl.textContent = `${startStr} – ${endStr}`;
	} else {
		dateEl.textContent = date.toLocaleDateString(locale, {
			year: "numeric",
			month: "long",
		});
	}
}

/* =========================
    THEME
========================= */

function getActiveCalendarTheme() {
	const h = getCSSHelpers();

	const borderMain = h.getVar("--ec-border");
	const borderMuted = h.getVar("--ec-border-muted");
	const ecText = h.getColor("--ec-text-color");
	const ecTextMuted = h.getColor("--ec-text-color-muted");
	const ecHoliday = h.getColor("--ec-text-holiday");
	const ecHolidayMuted = h.getColor("--ec-text-holiday-muted");
	const ecAccent = h.getColor("--ec-accent");
	const bgCalBody = h.getColor("--ec-bg-cal-body");
	const bgCalHeader = h.getColor("--ec-bg-cal-header");
	const bgCalPopup = h.getColor("--ec-bg-cal-popup");
	const bgToday = h.getColor("--ec-bg-accent-today");
	const bgWeekend = h.getColor("--ec-bg-weekend");
	const gridLeftWidthNum = h.getNum("--ec-grid-left-width");
	const headerHeightNum = h.getNum("--ec-header-height");

	const theme = {
		common: {
			backgroundColor: bgCalBody,
			border: borderMain,
			dayName: { color: ecText },
			saturday: { color: ecText },
			holiday: { color: ecHoliday },
			today: { color: "white" },
			gridSelection: {
				backgroundColor: bgToday,
				border: `1px solid ${ecAccent}`,
			},
		},
		month: {
			dayName: {
				borderLeft: "none",
				backgroundColor: bgCalHeader,
			},
			dayExceptThisMonth: { color: ecTextMuted },
			holidayExceptThisMonth: { color: ecHolidayMuted },
			weekend: { backgroundColor: bgWeekend },
			moreView: {
				backgroundColor: bgCalPopup,
				border: borderMuted,
				boxShadow: "0 2px 6px rgba(0, 0, 0, 0.1)",
				width: null,
				height: 200,
			},
			moreViewTitle: {
				backgroundColor: bgCalPopup,
			},
			gridCell: {
				headerHeight: headerHeightNum,
				footerHeight: null,
			},
		},
		week: {
			dayName: {
				borderLeft: "none",
				borderTop: "none",
				borderBottom: "none",
				backgroundColor: bgCalHeader,
			},

			dayGrid: {
				borderRight: borderMain,
				backgroundColor: "transparent",
			},
			dayGridLeft: {
				width: gridLeftWidthNum,
				borderRight: borderMain,
				backgroundColor: "transparent",
			},
			timeGrid: { borderRight: borderMain },
			timeGridLeft: {
				width: gridLeftWidthNum,
				borderRight: borderMain,
				backgroundColor: "transparent",
			},
			timeGridLeftAdditionalTimezone: {
				backgroundColor: bgWeekend,
			},
			timeGridHalfHourLine: { borderBottom: "none" },
			timeGridHourLine: { borderBottom: borderMuted },
			today: {
				color: ecAccent,
				backgroundColor: bgToday,
			},
			weekend: { backgroundColor: bgWeekend },
			pastDay: { color: ecTextMuted },
			pastTime: { color: ecTextMuted },
			futureTime: { color: ecText },
			nowIndicatorLabel: {
				color: ecAccent,
			},
			nowIndicatorPast: { border: `1px dashed ${ecAccent}` },
			nowIndicatorBullet: { backgroundColor: ecAccent },
			nowIndicatorToday: { border: `1px solid ${ecAccent}` },
			nowIndicatorFuture: { border: "none" },
			panelResizer: { border: borderMain },
			gridSelection: { color: ecAccent },
		},
	};

	h.cleanup();

	return theme;
}

/* =========================
    UPDATE ALL THEMES
========================= */
function updateAllCalendarThemes() {
	const theme = getActiveCalendarTheme();

	document.querySelectorAll(".ec-calendar").forEach(el => {
		const calendar = el._tuiCalendar;
		if (!calendar) return;

		calendar.setTheme(theme);
		calendar.render();
	});

	// Restore custom formatting after theme update
	requestAnimationFrame(() => {
		replaceMoreText();
	});
}

/* =========================
    POPUP OBSERVER
========================= */
function observePopups() {
	const observer = new MutationObserver(mutations => {
		for (const mutation of mutations) {
			for (const node of mutation.addedNodes) {
				if (node.nodeType !== 1) continue; // Skip non-element nodes

				// Check if popup appeared
				const isPopup = node.classList?.contains(
					"toastui-calendar-see-more-container",
				);
				const hasPopup = node.querySelector?.(
					".toastui-calendar-see-more-container",
				);

				if (isPopup || hasPopup) {
					requestAnimationFrame(replaceDayNames);
				}
			}
		}
	});

	observer.observe(document.body, {
		childList: true,
		subtree: true,
	});
}

/* =========================
    REPLACE DAY NAMES
========================= */
function replaceDayNames() {
	const dayEls = document.querySelectorAll(".toastui-calendar-more-title-day");

	// Weź dayNames z pierwszego dostępnego kalendarza
	const firstCalendarData = Object.values(window.eventCalendarData)[0];
	if (!firstCalendarData?.dayNamesShort) return;

	dayEls.forEach(el => {
		const currentText = el.textContent.trim();
		const englishDayMap = {
			Sun: 0,
			Mon: 1,
			Tue: 2,
			Wed: 3,
			Thu: 4,
			Fri: 5,
			Sat: 6,
		};

		const dayIndex = englishDayMap[currentText];
		if (dayIndex !== undefined && firstCalendarData.dayNamesShort[dayIndex]) {
			el.textContent = firstCalendarData.dayNamesShort[dayIndex];
		}
	});
}

/* =========================
    THEME OBSERVER
========================= */
function initThemeObserver() {
	let debounceTimer;

	const scheduleUpdate = () => {
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(updateAllCalendarThemes, 100);
	};

	// System theme
	const mq = window.matchMedia?.("(prefers-color-scheme: dark)");
	if (mq) {
		mq.addEventListener("change", scheduleUpdate);
	}

	// ACSS toggle (observe <html> class)
	const observer = new MutationObserver(mutations => {
		if (mutations.some(m => m.attributeName === "class")) {
			scheduleUpdate();
		}
	});

	observer.observe(document.documentElement, {
		attributes: true,
		attributeFilter: ["class"],
	});

	// Cleanup
	window.addEventListener("beforeunload", () => {
		observer.disconnect();
		clearTimeout(debounceTimer);
	});
}
