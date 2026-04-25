const { registerPlugin } = wp.plugins;
const { PluginDocumentSettingPanel } = wp.editor;
const { CheckboxControl, TextControl, ColorPalette } = wp.components;
const { useSelect, useDispatch } = wp.data;
const { createElement, Fragment, useState } = wp.element;

const L = window.eventCalendarSidebarLabels || {};

// --- Helper Functions (poza komponentem) ---

const toggleAllDay = (value, meta, eventStart, eventEnd, editPost) => {
	const updates = value
		? {
				_event_all_day: "1",
				_event_start: eventStart ? eventStart.split("T")[0] : "",
				_event_end: eventEnd ? eventEnd.split("T")[0] : "",
			}
		: {
				_event_all_day: "0",
				_event_start:
					eventStart && !eventStart.includes("T")
						? eventStart + "T00:00"
						: eventStart,
				_event_end:
					eventEnd && !eventEnd.includes("T") ? eventEnd + "T23:59" : eventEnd,
			};

	editPost({ meta: { ...meta, ...updates } });
};

const validateAndFormatDates = (
	field,
	value,
	eventStart,
	eventEnd,
	setWarning,
) => {
	let newStart = field === "start" ? value : eventStart;
	let newEnd = field === "end" ? value : eventEnd;

	const formatDateTime = val =>
		val && !val.includes("T") ? val + "T00:00" : val;
	newStart = formatDateTime(newStart);
	newEnd = formatDateTime(newEnd);

	if (newStart && newEnd) {
		const startMs = new Date(newStart).getTime();
		const endMs = new Date(newEnd).getTime();

		if (endMs < startMs) {
			newEnd = newStart;
			if (setWarning && L.endBeforeStartWarning) {
				setWarning(L.endBeforeStartWarning);
				setTimeout(() => setWarning(""), 3000);
			}
		}
	}

	return { newStart, newEnd };
};

// --- Main Component ---

const EventSidebar = () => {
	const [warning, setWarning] = useState("");

	const postType = useSelect(
		select => select("core/editor").getCurrentPostType(),
		[],
	);

	const meta = useSelect(
		select => select("core/editor").getEditedPostAttribute("meta") ?? {},
		[],
	);

	const { editPost } = useDispatch("core/editor");

	// --- Page notice ---
	if (postType === "page") {
		return createElement(
			PluginDocumentSettingPanel,
			{
				name: "event-calendar-sidebar",
				title: L.title,
				className: "event-calendar-sidebar",
			},
			createElement("p", { className: "wp-block-page-notice" }, L.pageNotice),
		);
	}

	const isEvent = meta?._is_event === "1";
	const eventStart = meta?._event_start || "";
	const eventEnd = meta?._event_end || "";
	const eventLocation = meta?._event_location || "";
	const eventAllDay = meta?._event_all_day === "1";
	const eventColor = meta?._event_color || "#d3c1ef";

	// --- Handlers ---

	const handleAllDayChange = value => {
		toggleAllDay(value, meta, eventStart, eventEnd, editPost);
	};

	const handleDateChange = (field, value) => {
		const { newStart, newEnd } = validateAndFormatDates(
			field,
			value,
			eventStart,
			eventEnd,
			setWarning,
		);

		editPost({
			meta: {
				...meta,
				_event_start: newStart,
				_event_end: newEnd,
			},
		});
	};

	const handleColorChange = value => {
		editPost({
			meta: {
				...meta,
				_event_color: value || "#d3c1ef",
			},
		});
	};

	// --- Render ---
	return createElement(
		PluginDocumentSettingPanel,
		{
			name: "event-calendar-sidebar",
			title: L.title,
			className: "event-calendar-sidebar",
		},
		createElement(
			Fragment,
			null,

			createElement(CheckboxControl, {
				label: L.includeLabel,
				help: L.includeHelp,
				checked: isEvent,
				onChange: value =>
					editPost({
						meta: {
							...meta,
							_is_event: value ? "1" : "0",
						},
					}),
			}),

			isEvent &&
				createElement(
					Fragment,
					null,

					createElement(CheckboxControl, {
						label: L.allDayLabel,
						help: L.allDayHelp,
						checked: eventAllDay,
						onChange: handleAllDayChange,
					}),

					createElement(TextControl, {
						label: eventAllDay ? L.startDate : L.startLabel,
						type: eventAllDay ? "date" : "datetime-local",
						value: eventStart,
						onChange: value => handleDateChange("start", value),
					}),

					createElement(TextControl, {
						label: eventAllDay ? L.endDate : L.endLabel,
						type: eventAllDay ? "date" : "datetime-local",
						value: eventEnd,
						min: eventStart,
						onChange: value => handleDateChange("end", value),
						help: warning || L.endDateHelp,
					}),

					warning &&
						createElement(
							"div",
							{ className: "notice notice-warning inline" },
							createElement("p", null, warning),
						),

					createElement(TextControl, {
						label: L.locationLabel,
						value: eventLocation,
						onChange: value =>
							editPost({
								meta: {
									...meta,
									_event_location: value,
								},
							}),
					}),

					createElement(
						"div",
						{ className: "components-base-control" },

						createElement(
							"label",
							{ className: "components-base-control__label" },
							L.colorLabel,
						),

						createElement(ColorPalette, {
							value: eventColor,
							onChange: handleColorChange,
							colors: [
								{ name: "Lavender", color: "#ddc7ff" },
								{ name: "Purple", color: "#e7adfd" },
								{ name: "Pink", color: "#ff96b9" },
								{ name: "Red", color: "#fda49a" },
								{ name: "Orange", color: "#facd8b" },
								{ name: "Yellow", color: "#ffe760" },
								{ name: "Lime", color: "#e1ed72" },
								{ name: "Light Green", color: "#c5f78d" },
								{ name: "Mint", color: "#9ff7c8" },
								{ name: "Cyan", color: "#98f3ff" },
								{ name: "Light Blue", color: "#9fdefb" },
								{ name: "Blue", color: "#a9d7fc" },
							],
							disableCustomColors: false,
						}),
					),
				),
		),
	);
};

registerPlugin("event-calendar-sidebar", { render: EventSidebar });
