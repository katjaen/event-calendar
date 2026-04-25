(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, CheckboxControl } = wp.components;
	const { useSelect } = wp.data;
	const { createElement: el, useMemo, Fragment } = wp.element;
	const { __, _n, sprintf } = wp.i18n;

	const labels = window.eventCalendarBlockLabels || {};

	registerBlockType("event-calendar/calendar", {
		edit: function Edit({ attributes, setAttributes }) {
			const { view, postType, taxonomy } = attributes;
			const terms = attributes.terms || [];
			const operator = attributes.operator || "IN";

			const blockProps = useBlockProps();

			// Fetch post types
			const postTypes = useSelect(function (select) {
				const types = select("core").getPostTypes({ per_page: -1 });
				return types || [];
			}, []);

			// Fetch taxonomies (filtered by selected post type)
			const taxonomies = useSelect(
				function (select) {
					const taxes = select("core").getTaxonomies({ per_page: -1 });
					if (!taxes) return [];

					// If no post type selected, show all taxonomies
					if (!postType) return taxes;

					// Filter taxonomies that apply to selected post type
					return taxes.filter(function (tax) {
						return tax.types && tax.types.includes(postType);
					});
				},
				[postType],
			);

			// Fetch terms for selected taxonomy
			const availableTerms = useSelect(
				function (select) {
					if (!taxonomy) return [];
					const terms = select("core").getEntityRecords("taxonomy", taxonomy, {
						per_page: -1,
					});
					return terms || [];
				},
				[taxonomy],
			);

			// Format post types options
			const postTypeOptions = useMemo(
				function () {
					const filtered = postTypes.filter(function (type) {
						return type.viewable && !["attachment", "page"].includes(type.slug);
					});
					return [{ label: labels.allPostTypes, value: "" }].concat(
						filtered.map(function (type) {
							return { label: type.name, value: type.slug };
						}),
					);
				},
				[postTypes],
			);

			// Format taxonomies options
			const taxonomyOptions = useMemo(
				function () {
					return [{ label: labels.noTaxonomy, value: "" }].concat(
						taxonomies.map(function (tax) {
							return { label: tax.name, value: tax.slug };
						}),
					);
				},
				[taxonomies],
			);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: labels.calendarSettings },
						el(SelectControl, {
							label: labels.view,
							value: view,
							options: [
								{ label: labels.month, value: "month" },
								{ label: labels.week, value: "week" },
								{ label: labels.day, value: "day" },
							],
							onChange: function (value) {
								setAttributes({ view: value });
							},
						}),
						el(SelectControl, {
							label: labels.postType,
							value: postType,
							options: postTypeOptions,
							onChange: function (value) {
								setAttributes({
									postType: value,
									taxonomy: "",
									terms: [],
								});
							},
							help: labels.postTypeHelp,
						}),
					),
					taxonomies.length > 0 &&
						el(
							PanelBody,
							{ title: labels.filters, initialOpen: false },
							el(SelectControl, {
								label: labels.taxonomy,
								value: taxonomy,
								options: taxonomyOptions,
								onChange: function (value) {
									setAttributes({ taxonomy: value, terms: [] });
								},
								help: labels.taxonomyHelp,
							}),
							taxonomy &&
								el(
									"div",
									null,
									terms.length > 0 &&
										el(
											"p",
											{ className: "components-base-control__help" },
											sprintf(
												_n(
													"✓ %d term selected",
													"✓ %d terms selected",
													terms.length,
													"event-calendar",
												),
												terms.length,
											),
										),
									[...availableTerms]
										.sort(function (a, b) {
											return a.name.localeCompare(b.name);
										})
										.map(function (term) {
											return el(CheckboxControl, {
												key: term.id,
												label: term.name + " (ID: " + term.id + ")",
												checked: terms.includes(Number(term.id)),
												onChange: function (isChecked) {
													const termId = Number(term.id);
													const newTerms = isChecked
														? [...terms, termId]
														: terms.filter(function (id) {
																return Number(id) !== termId;
															});

													setAttributes({ terms: newTerms });
												},
											});
										}),
									terms.length > 0 &&
										el(
											"button",
											{
												type: "button",
												className: "components-button is-secondary is-small",
												style: { marginTop: "8px" },
												onClick: function () {
													setAttributes({ terms: [] });
												},
											},
											"✕ " + __("Clear all", "event-calendar"),
										),
									el(SelectControl, {
										label: labels.operator,
										value: operator,
										options: [
											{ label: labels.operatorIN, value: "IN" },
											{ label: labels.operatorAND, value: "AND" },
											{ label: labels.operatorNOTIN, value: "NOT IN" },
										],
										onChange: function (value) {
											setAttributes({ operator: value });
										},
									}),
								),
						),
				),
				el(
					"div",
					blockProps,
					el(
						"div",
						{
							style: {
								padding: "20px",
								border: "2px dashed #ccc",
								textAlign: "center",
							},
						},
						el("p", null, el("strong", null, "📅 " + labels.eventCalendar)),
						el(
							"p",
							{ className: "components-base-control__help" },
							labels.view +
								": " +
								(labels[view] || view) +
								(postType ? " | " + labels.postType + ": " + postType : "") +
								(taxonomy
									? " | " +
										labels.taxonomy +
										": " +
										taxonomy +
										(terms.length > 0 ? " (" + terms.length + ")" : "")
									: ""),
						),
					),
				),
			);
		},

		save: function () {
			return null; // Dynamic block - rendered in PHP
		},
	});
})(window.wp);
