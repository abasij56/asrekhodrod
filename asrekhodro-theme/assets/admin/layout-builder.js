(function () {
	'use strict';

	const cfg = window.akLayoutBuilder;
	if (!cfg || !cfg.pages) {
		return;
	}

	const ZONE_ORDER = ['before_main', 'main', 'main_after', 'sidebar', 'after_main'];
	const i18n = cfg.i18n || {};

	const state = {
		currentPage: Object.keys(cfg.pages)[0] || 'front_page',
		pages: {},
		touchedPages: new Set(),
		useManifestDefault: new Set(),
		dirty: false,
		saving: false,
		message: '',
		messageType: 'info',
		modal: null,
		drag: null,
	};

	function uid() {
		return 'lb_' + Math.random().toString(36).slice(2, 11);
	}

	function cloneRow(row) {
		const copy = Object.assign({}, row);
		copy._uid = row._uid || uid();
		if (Array.isArray(copy.data_manual_posts)) {
			copy.data_manual_posts = copy.data_manual_posts.slice();
		}
		if (copy.data_manual_post_titles && typeof copy.data_manual_post_titles === 'object') {
			copy.data_manual_post_titles = Object.assign({}, copy.data_manual_post_titles);
		}
		return copy;
	}

	function manualTitleKey(postId) {
		return String(postId);
	}

	function loadManualTitlesFromDraft(draft) {
		const titles = {};
		const stored = draft.data_manual_post_titles;
		if (stored && typeof stored === 'object') {
			Object.keys(stored).forEach(function (key) {
				titles[key] = stored[key];
			});
		}
		return titles;
	}

	function manualTitleForPost(postId, manualTitles, draft) {
		const key = manualTitleKey(postId);
		return manualTitles[postId]
			|| manualTitles[key]
			|| (draft.data_manual_post_titles && draft.data_manual_post_titles[key])
			|| ('#' + postId);
	}

	function syncManualTitleStore(draft, manualTitles) {
		if (!Array.isArray(draft.data_manual_posts) || draft.data_manual_posts.length === 0) {
			draft.data_manual_post_titles = {};
			return;
		}

		const stored = {};
		draft.data_manual_posts.forEach(function (postId) {
			const key = manualTitleKey(postId);
			const title = manualTitles[postId] || manualTitles[key];
			if (title) {
				stored[key] = title;
			}
		});
		draft.data_manual_post_titles = stored;
	}

	function resolveMissingManualTitles(draft, manualTitles, done) {
		const ids = draft.data_manual_posts || [];
		const missing = ids.filter(function (postId) {
			const key = manualTitleKey(postId);
			return !manualTitles[postId] && !manualTitles[key];
		});

		if (!missing.length) {
			if (done) {
				done();
			}
			return;
		}

		const url = new URL(cfg.ajaxUrl);
		url.searchParams.set('action', 'ak_resolve_layout_manual_posts');
		url.searchParams.set('nonce', cfg.nonce);
		url.searchParams.set('ids', missing.join(','));

		fetch(url.toString(), { credentials: 'same-origin' })
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (json.success && json.data && json.data.titles) {
					Object.keys(json.data.titles).forEach(function (key) {
						manualTitles[key] = json.data.titles[key];
					});
				}
				if (done) {
					done();
				}
			})
			.catch(function () {
				if (done) {
					done();
				}
			});
	}

	function cloneDefaults(pageKey) {
		const page = cfg.pages[pageKey];
		if (!page) {
			return [];
		}
		return (page.defaults || []).map(function (row) {
			return cloneRow(Object.assign({ placement_page: pageKey }, row));
		});
	}

	function initState() {
		Object.keys(cfg.pages).forEach(function (pageKey) {
			const saved = cfg.placements && cfg.placements[pageKey];
			const hasSaved = Array.isArray(saved) && saved.length > 0;
			const source = hasSaved ? saved : (cfg.pages[pageKey].defaults || []);
			state.pages[pageKey] = source.map(function (row) {
				return cloneRow(Object.assign({ placement_page: pageKey }, row));
			});
		});
	}

	function markTouched(pageKey) {
		state.touchedPages.add(pageKey);
		state.useManifestDefault.delete(pageKey);
		state.dirty = true;
	}

	function shouldPersistPage(pageKey) {
		if (state.useManifestDefault.has(pageKey)) {
			return false;
		}
		if (state.touchedPages.has(pageKey)) {
			return true;
		}
		const saved = cfg.placements && cfg.placements[pageKey];
		return Array.isArray(saved) && saved.length > 0;
	}

	function serializePlacements() {
		const all = [];
		const pagesToSave = new Set();
		const clearPages = [];

		Object.keys(state.pages).forEach(function (pageKey) {
			if (state.useManifestDefault.has(pageKey)) {
				clearPages.push(pageKey);
				return;
			}
			if (shouldPersistPage(pageKey)) {
				pagesToSave.add(pageKey);
			}
		});

		state.touchedPages.forEach(function (pageKey) {
			if (!state.useManifestDefault.has(pageKey)) {
				pagesToSave.add(pageKey);
			}
		});

		pagesToSave.forEach(function (pageKey) {
			(state.pages[pageKey] || []).forEach(function (row) {
				const clean = {};
				Object.keys(row).forEach(function (key) {
					if (key === '_uid') {
						return;
					}
					clean[key] = row[key];
				});
				clean.placement_page = pageKey;
				all.push(clean);
			});
		});

		return { placements: all, clearPages: clearPages };
	}

	function dragUidFromEvent(e) {
		if (!e || !e.dataTransfer) {
			return '';
		}
		return e.dataTransfer.getData('application/x-ak-lb-uid')
			|| e.dataTransfer.getData('text/plain')
			|| (state.drag && state.drag.uid ? state.drag.uid : '');
	}

	function isDataConfigurable(meta) {
		return meta.dataConfigurable !== false;
	}

	function hasTitleField(meta) {
		return true;
	}

	function ensureTitleDraft(draft, pageKey, zoneKey) {
		const meta = blockMeta(pageKey, zoneKey, draft.placement_block);
		if (draft.data_title === undefined || draft.data_title === null) {
			draft.data_title = meta.defaultTitle || '';
		}
	}

	function renderTitleField(draft, meta) {
		const current = draft.data_title !== undefined && draft.data_title !== null
			? String(draft.data_title)
			: (meta.defaultTitle || '');
		const placeholder = meta.defaultTitle || meta.label || '';

		return el('div', { className: 'ak-lb-field' }, [
			el('label', { text: i18n.blockTitle || 'عنوان بخش' }),
			el('p', { className: 'ak-lb-field-hint', text: i18n.blockTitleHint || 'خالی = بدون عنوان' }),
			(function () {
				const input = el('input', {
					type: 'text',
					value: current,
					placeholder: placeholder,
				});
				input.addEventListener('input', function () {
					draft.data_title = input.value;
				});
				return input;
			})(),
		]);
	}

	function blockMeta(pageKey, zoneKey, blockName) {
		const zone = cfg.pages[pageKey] && cfg.pages[pageKey].zones[zoneKey];
		if (!zone || !zone.blocks || !zone.blocks[blockName]) {
			return { label: blockName, dataConfigurable: false, defaults: {} };
		}
		return zone.blocks[blockName];
	}

	function rowsForZone(pageKey, zoneKey) {
		return (state.pages[pageKey] || []).filter(function (row) {
			return row.placement_zone === zoneKey;
		});
	}

	function blockSummary(row, pageKey) {
		const meta = blockMeta(pageKey, row.placement_zone, row.placement_block);
		const parts = [];
		const titleLabel = row.data_title != null ? String(row.data_title).trim() : '';
		if (titleLabel) {
			parts.push('«' + titleLabel + '»');
		}
		if (isDataConfigurable(meta)) {
			if (row.data_post_type && cfg.postTypes[row.data_post_type]) {
				parts.push(cfg.postTypes[row.data_post_type]);
			}
			if (row.data_count) {
				parts.push('تعداد: ' + row.data_count);
			}
			if (row.data_strategy && cfg.strategies[row.data_strategy]) {
				parts.push(cfg.strategies[row.data_strategy]);
			}
			if (row.data_category) {
				const cat = (cfg.categories || []).find(function (c) {
					return c.id === parseInt(row.data_category, 10);
				});
				if (cat) {
					parts.push(cat.name);
				}
			}
			if (row.data_strategy === 'manual' && Array.isArray(row.data_manual_posts) && row.data_manual_posts.length) {
				parts.push(row.data_manual_posts.length + ' انتخابی');
			}
		} else {
			parts.push(meta.source || 'ثابت');
		}
		return parts.join(' · ') || meta.label;
	}

	function setMessage(text, type) {
		state.message = text;
		state.messageType = type || 'info';
		render();
	}

	function esc(text) {
		const el = document.createElement('span');
		el.textContent = text == null ? '' : String(text);
		return el.innerHTML;
	}

	function el(tag, attrs, children) {
		const node = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (key) {
				if (key === 'className') {
					node.className = attrs[key];
				} else if (key === 'text') {
					node.textContent = attrs[key];
				} else if (key === 'html') {
					node.innerHTML = attrs[key];
				} else if (key === 'disabled') {
					node.disabled = !!attrs[key];
				} else if (key.slice(0, 2) === 'on' && typeof attrs[key] === 'function') {
					node.addEventListener(key.slice(2).toLowerCase(), attrs[key]);
				} else if (attrs[key] != null && attrs[key] !== false) {
					node.setAttribute(key, attrs[key]);
				}
			});
		}
		if (children) {
			children.forEach(function (child) {
				if (child == null) {
					return;
				}
				node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
			});
		}
		return node;
	}

	function moveBlockInZone(pageKey, zoneKey, uid, direction) {
		const zoneRows = rowsForZone(pageKey, zoneKey);
		const idx = zoneRows.findIndex(function (r) {
			return r._uid === uid;
		});
		if (idx === -1) {
			return;
		}
		const targetIdx = idx + direction;
		if (targetIdx < 0 || targetIdx >= zoneRows.length) {
			return;
		}
		reorderWithinZone(pageKey, zoneKey, uid, zoneRows[targetIdx]._uid);
	}

	function stopDragOnControl(e) {
		e.preventDefault();
		e.stopPropagation();
	}

	function beginBlockDrag(e, card, pageKey, zoneKey, uid) {
		state.drag = { pageKey: pageKey, zoneKey: zoneKey, uid: uid };
		if (e.dataTransfer) {
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('application/x-ak-lb-uid', uid);
			e.dataTransfer.setData('text/plain', uid);
		}
		card.classList.add('is-dragging');
	}

	function endBlockDrag(card) {
		card.classList.remove('is-dragging');
		document.querySelectorAll('.ak-lb-block.is-drag-over').forEach(function (n) {
			n.classList.remove('is-drag-over');
		});
		window.setTimeout(function () {
			state.drag = null;
		}, 0);
	}

	function renderBlockCard(pageKey, zoneKey, row, index) {
		const meta = blockMeta(pageKey, zoneKey, row.placement_block);
		const zoneRows = rowsForZone(pageKey, zoneKey);
		const isFirst = index <= 0;
		const isLast = index >= zoneRows.length - 1;
		let card;

		const grip = el('span', {
			className: 'ak-lb-block-grip',
			text: '⋮⋮',
			draggable: 'true',
			title: i18n.dragHandle || 'کشیدن برای جابجایی',
			ondragstart: function (e) {
				e.stopPropagation();
				beginBlockDrag(e, card, pageKey, zoneKey, row._uid);
			},
			ondragend: function () {
				endBlockDrag(card);
			},
		});

		card = el('div', {
			className: 'ak-lb-block',
			'data-uid': row._uid,
			ondragover: function (e) {
				if (!state.drag || state.drag.zoneKey !== zoneKey) {
					return;
				}
				e.preventDefault();
				if (e.dataTransfer) {
					e.dataTransfer.dropEffect = 'move';
				}
				card.classList.add('is-drag-over');
			},
			ondragleave: function (e) {
				if (e.relatedTarget && card.contains(e.relatedTarget)) {
					return;
				}
				card.classList.remove('is-drag-over');
			},
			ondrop: function (e) {
				e.preventDefault();
				e.stopPropagation();
				card.classList.remove('is-drag-over');
				const fromUid = dragUidFromEvent(e);
				if (!fromUid || fromUid === row._uid) {
					return;
				}
				reorderWithinZone(pageKey, zoneKey, fromUid, row._uid);
			},
		}, [
			grip,
			el('div', { className: 'ak-lb-block-info' }, [
				el('div', { className: 'ak-lb-block-name', text: meta.label || row.placement_block }),
				el('div', { className: 'ak-lb-block-meta', text: blockSummary(row, pageKey) }),
			]),
			el('div', {
				className: 'ak-lb-block-actions',
				draggable: 'false',
				ondragstart: stopDragOnControl,
			}, [
				el('div', { className: 'ak-lb-move-group' }, [
					el('button', {
						type: 'button',
						className: 'ak-lb-icon-btn ak-lb-icon-btn--move',
						title: i18n.moveUp || 'بالا',
						text: '▲',
						disabled: isFirst,
						draggable: 'false',
						onmousedown: stopDragOnControl,
						onclick: function (e) {
							stopDragOnControl(e);
							moveBlockInZone(pageKey, zoneKey, row._uid, -1);
						},
					}),
					el('button', {
						type: 'button',
						className: 'ak-lb-icon-btn ak-lb-icon-btn--move',
						title: i18n.moveDown || 'پایین',
						text: '▼',
						disabled: isLast,
						draggable: 'false',
						onmousedown: stopDragOnControl,
						onclick: function (e) {
							stopDragOnControl(e);
							moveBlockInZone(pageKey, zoneKey, row._uid, 1);
						},
					}),
				]),
				el('button', {
					type: 'button',
					className: 'ak-lb-icon-btn',
					title: i18n.editBlock || 'ویرایش',
					text: '✎',
					draggable: 'false',
					onmousedown: stopDragOnControl,
					onclick: function (e) {
						stopDragOnControl(e);
						openModal(pageKey, zoneKey, row);
					},
				}),
				el('button', {
					type: 'button',
					className: 'ak-lb-icon-btn ak-lb-icon-btn--danger',
					title: i18n.removeBlock || 'حذف',
					text: '×',
					draggable: 'false',
					onmousedown: stopDragOnControl,
					onclick: function (e) {
						stopDragOnControl(e);
						if (!window.confirm(i18n.confirmRemove || 'حذف شود؟')) {
							return;
						}
						removeRow(pageKey, row._uid);
					},
				}),
			]),
		]);
		return card;
	}

	function renderZone(pageKey, zoneKey, zoneDef) {
		const rows = rowsForZone(pageKey, zoneKey);
		const canAdd = zoneDef.multiple || rows.length === 0;
		const blocksEl = el('div', {
			className: 'ak-lb-blocks' + (rows.length ? '' : ' is-empty'),
		});

		blocksEl.addEventListener('dragover', function (e) {
			if (!state.drag || state.drag.zoneKey !== zoneKey) {
				return;
			}
			e.preventDefault();
			if (e.dataTransfer) {
				e.dataTransfer.dropEffect = 'move';
			}
		});

		blocksEl.addEventListener('drop', function (e) {
			e.preventDefault();
			e.stopPropagation();
			const fromUid = dragUidFromEvent(e);
			const zoneRows = rowsForZone(pageKey, zoneKey);
			if (!fromUid || !zoneRows.length) {
				return;
			}
			const lastRow = zoneRows[zoneRows.length - 1];
			if (lastRow && fromUid !== lastRow._uid) {
				reorderWithinZone(pageKey, zoneKey, fromUid, lastRow._uid);
			}
		});

		if (!rows.length) {
			blocksEl.appendChild(el('div', { className: 'ak-lb-empty', text: i18n.emptyZone || '' }));
		} else {
			rows.forEach(function (row, index) {
				blocksEl.appendChild(renderBlockCard(pageKey, zoneKey, row, index));
			});
		}

		const body = el('div', { className: 'ak-lb-zone-body' }, [blocksEl]);

		if (canAdd) {
			body.appendChild(el('button', {
				type: 'button',
				className: 'ak-lb-add-btn',
				text: '+ ' + (i18n.addBlock || 'افزودن بلاک'),
				onclick: function () {
					openModal(pageKey, zoneKey, null);
				},
			}));
		} else {
			body.appendChild(el('button', {
				type: 'button',
				className: 'ak-lb-add-btn',
				disabled: 'disabled',
				text: 'فقط یک بلاک در این موقعیت',
			}));
		}

		return el('div', { className: 'ak-lb-zone ak-lb-zone--' + zoneKey }, [
			el('div', { className: 'ak-lb-zone-head' }, [
				el('span', { className: 'ak-lb-zone-title', text: zoneDef.label || zoneKey }),
				el('span', { className: 'ak-lb-zone-key', text: zoneKey }),
			]),
			body,
		]);
	}

	function renderCanvas(pageKey) {
		const page = cfg.pages[pageKey];
		if (!page) {
			return el('div');
		}

		const zones = page.zones || {};
		const layoutMode = page.layout_mode || 'blocks';
		const ordered = ZONE_ORDER.filter(function (z) {
			return zones[z];
		});

		const canvas = el('div', { className: 'ak-lb-canvas' }, [
			el('div', { className: 'ak-lb-canvas-label', text: 'پیش‌نمایش ساختار — ' + (page.label || pageKey) }),
		]);

		const before = ordered.filter(function (z) {
			return z === 'before_main';
		});
		const middle = ordered.filter(function (z) {
			return z === 'main' || z === 'main_after' || z === 'sidebar';
		});
		const after = ordered.filter(function (z) {
			return z === 'after_main';
		});

		before.forEach(function (zoneKey) {
			canvas.appendChild(renderZone(pageKey, zoneKey, zones[zoneKey]));
		});

		if (middle.length) {
			const hasMain = middle.indexOf('main') !== -1;
			const hasSidebar = middle.indexOf('sidebar') !== -1;
			const rowClass = hasMain ? 'ak-lb-row' : 'ak-lb-row ak-lb-row--sidebar-only';
			const row = el('div', { className: rowClass });

			if (hasMain || middle.indexOf('main_after') !== -1) {
				const mainCol = el('div', { className: 'ak-lb-main-col' });
				if (middle.indexOf('main') !== -1) {
					mainCol.appendChild(renderZone(pageKey, 'main', zones.main));
				}
				if (layoutMode === 'system') {
					mainCol.appendChild(el('div', {
						className: 'ak-lb-system-slot',
						text: i18n.systemContent || 'محتوای سیستمی (ثابت در قالب)',
					}));
				}
				if (middle.indexOf('main_after') !== -1) {
					mainCol.appendChild(renderZone(pageKey, 'main_after', zones.main_after));
				}
				row.appendChild(mainCol);
			}

			if (hasSidebar) {
				row.appendChild(renderZone(pageKey, 'sidebar', zones.sidebar));
			}
			canvas.appendChild(row);
		}

		after.forEach(function (zoneKey) {
			canvas.appendChild(renderZone(pageKey, zoneKey, zones[zoneKey]));
		});

		return canvas;
	}

	function renderModal() {
		if (!state.modal) {
			return null;
		}

		const m = state.modal;
		const zoneDef = cfg.pages[m.pageKey].zones[m.zoneKey];
		const blockChoices = zoneDef.blocks || {};
		const draft = m.draft;
		const meta = blockMeta(m.pageKey, m.zoneKey, draft.placement_block);
		const fields = [];

		if (m.editing) {
			fields.push(el('div', { className: 'ak-lb-field' }, [
				el('label', { text: i18n.blockLabel || 'بلاک' }),
				el('div', { className: 'ak-lb-block-readonly', text: meta.label || draft.placement_block }),
			]));
		} else {
			fields.push(el('div', { className: 'ak-lb-field' }, [
				el('label', { text: i18n.selectBlock || 'بلاک' }),
				(function () {
					const select = el('select');
					Object.keys(blockChoices).forEach(function (blockKey) {
						const opt = el('option', {
							value: blockKey,
							text: blockChoices[blockKey].label || blockKey,
						});
						if (draft.placement_block === blockKey) {
							opt.selected = true;
						}
						select.appendChild(opt);
					});
					select.addEventListener('change', function () {
						draft.placement_block = select.value;
						delete draft.data_title;
						applyBlockDefaults(draft, m.pageKey, m.zoneKey);
						render();
					});
					return select;
				})(),
			]));
		}

		if (hasTitleField(meta)) {
			fields.push(renderTitleField(draft, meta));
		}

		if (isDataConfigurable(meta)) {
			renderDataFields(m, draft, meta).forEach(function (field) {
				fields.push(field);
			});
		}

		return el('div', {
			className: 'ak-lb-modal-backdrop',
			onclick: function (e) {
				if (e.target.classList.contains('ak-lb-modal-backdrop')) {
					closeModal();
				}
			},
		}, [
			el('div', { className: 'ak-lb-modal', role: 'dialog', 'aria-modal': 'true' }, [
				el('div', { className: 'ak-lb-modal-head', text: m.editing ? (i18n.editBlock || 'ویرایش') : (i18n.addBlock || 'افزودن') }),
				el('div', { className: 'ak-lb-modal-body' }, fields),
				el('div', { className: 'ak-lb-modal-foot' }, [
					el('button', {
						type: 'button',
						className: 'ak-lb-btn ak-lb-btn--primary',
						text: m.editing ? 'ذخیره تغییرات' : 'افزودن',
						onclick: function () {
							saveModal();
						},
					}),
					el('button', {
						type: 'button',
						className: 'ak-lb-btn',
						text: i18n.cancel || 'انصراف',
						onclick: closeModal,
					}),
				]),
			]),
		]);
	}

	function searchPosts(query, postType, container, draft, modalState) {
		container.innerHTML = '';
		const url = new URL(cfg.ajaxUrl);
		url.searchParams.set('action', 'ak_search_posts_for_layout');
		url.searchParams.set('nonce', cfg.nonce);
		url.searchParams.set('q', query);
		url.searchParams.set('post_type', postType || 'post');

		fetch(url.toString(), { credentials: 'same-origin' })
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (!json.success || !json.data || !Array.isArray(json.data.items)) {
					return;
				}
				json.data.items.forEach(function (item) {
					const btn = el('button', {
						type: 'button',
						className: 'ak-lb-manual-item',
						text: item.title,
						onclick: function () {
							if (!Array.isArray(draft.data_manual_posts)) {
								draft.data_manual_posts = [];
							}
							if (draft.data_manual_posts.indexOf(item.id) === -1) {
								draft.data_manual_posts.push(item.id);
								modalState.manualTitles[item.id] = item.title;
								modalState.manualTitles[manualTitleKey(item.id)] = item.title;
							}
							render();
						},
					});
					container.appendChild(btn);
				});
			})
			.catch(function () {
				/* ignore */
			});
	}

	function applyBlockDefaults(draft, pageKey, zoneKey) {
		const meta = blockMeta(pageKey, zoneKey, draft.placement_block);
		if (draft.data_title === undefined || draft.data_title === null) {
			draft.data_title = meta.defaultTitle || '';
		}
		const defs = meta.defaults || {};
		if (!draft.data_post_type) {
			draft.data_post_type = defs.post_type || 'post';
		}
		if (!draft.data_count) {
			draft.data_count = defs.count || 10;
		}
		if (!draft.data_strategy) {
			draft.data_strategy = defs.strategy || 'latest';
		}
		if (draft.data_category === undefined || draft.data_category === null) {
			draft.data_category = defs.category || '';
		}
		if (!Array.isArray(draft.data_manual_posts)) {
			draft.data_manual_posts = [];
		}
	}

	function renderDataFields(m, draft, meta) {
		const fields = [];
		const labels = i18n;

		fields.push(el('div', { className: 'ak-lb-field-section' }, [
			el('div', { className: 'ak-lb-field-section-title', text: labels.dataSettings || 'تنظیمات داده' }),
		]));

		fields.push(el('div', { className: 'ak-lb-field' }, [
			el('label', { text: labels.postType || 'نوع پست' }),
			(function () {
				const select = el('select');
				Object.keys(cfg.postTypes || {}).forEach(function (pt) {
					const opt = el('option', { value: pt, text: cfg.postTypes[pt] });
					if ((draft.data_post_type || 'post') === pt) {
						opt.selected = true;
					}
					select.appendChild(opt);
				});
				select.addEventListener('change', function () {
					draft.data_post_type = select.value;
				});
				return select;
			})(),
		]));

		fields.push(el('div', { className: 'ak-lb-field' }, [
			el('label', { text: labels.category || 'دسته‌بندی' }),
			(function () {
				const select = el('select');
				select.appendChild(el('option', { value: '', text: labels.categoryAll || '— همه —' }));
				(cfg.categories || []).forEach(function (cat) {
					const opt = el('option', { value: String(cat.id), text: cat.name });
					if (String(draft.data_category || '') === String(cat.id)) {
						opt.selected = true;
					}
					select.appendChild(opt);
				});
				select.addEventListener('change', function () {
					draft.data_category = select.value ? parseInt(select.value, 10) : '';
				});
				return select;
			})(),
		]));

		if (draft.data_strategy !== 'manual') {
			fields.push(el('div', { className: 'ak-lb-field' }, [
				el('label', { text: labels.count || 'تعداد' }),
				(function () {
					const input = el('input', {
						type: 'number',
						min: '1',
						max: '40',
						value: String(draft.data_count || 10),
					});
					input.addEventListener('input', function () {
						draft.data_count = parseInt(input.value, 10) || 10;
					});
					return input;
				})(),
			]));
		}

		fields.push(el('div', { className: 'ak-lb-field' }, [
			el('label', { text: labels.strategy || 'نحوه گزینش' }),
			(function () {
				const select = el('select');
				Object.keys(cfg.strategies || {}).forEach(function (key) {
					const opt = el('option', { value: key, text: cfg.strategies[key] });
					if ((draft.data_strategy || 'latest') === key) {
						opt.selected = true;
					}
					select.appendChild(opt);
				});
				select.addEventListener('change', function () {
					draft.data_strategy = select.value;
					render();
				});
				return select;
			})(),
		]));

		if (draft.data_strategy === 'manual') {
			const chips = el('div', { className: 'ak-lb-chips' });
			(draft.data_manual_posts || []).forEach(function (postId) {
				const title = manualTitleForPost(postId, m.manualTitles, draft);
				chips.appendChild(el('span', { className: 'ak-lb-chip' }, [
					document.createTextNode(title),
					el('button', {
						type: 'button',
						text: '×',
						onclick: function () {
							draft.data_manual_posts = (draft.data_manual_posts || []).filter(function (id) {
								return id !== postId;
							});
							delete m.manualTitles[postId];
							delete m.manualTitles[manualTitleKey(postId)];
							if (draft.data_manual_post_titles) {
								delete draft.data_manual_post_titles[manualTitleKey(postId)];
							}
							render();
						},
					}),
				]));
			});

			const searchInput = el('input', { type: 'search', placeholder: labels.manualPosts || '' });
			const results = el('div', { className: 'ak-lb-manual-list' });

			let timer = null;
			searchInput.addEventListener('input', function () {
				clearTimeout(timer);
				timer = setTimeout(function () {
					searchPosts(searchInput.value, draft.data_post_type || 'post', results, draft, m);
				}, 280);
			});

			fields.push(el('div', { className: 'ak-lb-field' }, [
				el('label', { text: 'آیتم‌های انتخابی' }),
				searchInput,
				results,
				chips,
			]));
		}

		return fields;
	}

	function openModal(pageKey, zoneKey, row) {
		const zoneDef = cfg.pages[pageKey].zones[zoneKey];
		const blockKeys = Object.keys(zoneDef.blocks || {});
		if (!blockKeys.length) {
			return;
		}

		const draft = row
			? cloneRow(row)
			: {
					placement_page: pageKey,
					placement_zone: zoneKey,
					placement_block: blockKeys[0],
					_uid: uid(),
			  };

		applyBlockDefaults(draft, pageKey, zoneKey);

		const manualTitles = loadManualTitlesFromDraft(draft);
		const modalUid = row ? row._uid : draft._uid;

		state.modal = {
			pageKey: pageKey,
			zoneKey: zoneKey,
			editing: !!row,
			uid: modalUid,
			draft: draft,
			manualTitles: manualTitles,
		};

		resolveMissingManualTitles(draft, manualTitles, function () {
			if (!state.modal || state.modal.uid !== modalUid) {
				return;
			}

			syncManualTitleStore(draft, manualTitles);

			if (row) {
				const rows = state.pages[pageKey] || [];
				const idx = rows.findIndex(function (r) {
					return r._uid === modalUid;
				});
				if (idx !== -1) {
					rows[idx] = cloneRow(Object.assign({}, rows[idx], {
						data_manual_post_titles: draft.data_manual_post_titles,
					}));
					markTouched(pageKey);
				}
			}

			render();
		});

		render();
	}

	function closeModal() {
		state.modal = null;
		render();
	}

	function saveModal() {
		const m = state.modal;
		if (!m) {
			return;
		}
		const draft = m.draft;
		draft.placement_page = m.pageKey;
		draft.placement_zone = m.zoneKey;
		ensureTitleDraft(draft, m.pageKey, m.zoneKey);
		if (draft.data_strategy === 'manual') {
			const selected = draft.data_manual_posts || [];
			if (selected.length > 0) {
				draft.data_count = selected.length;
			}
		}
		syncManualTitleStore(draft, m.manualTitles);

		const rows = state.pages[m.pageKey] || [];
		if (m.editing) {
			const idx = rows.findIndex(function (r) {
				return r._uid === m.uid;
			});
			if (idx !== -1) {
				rows[idx] = cloneRow(draft);
			}
		} else {
			rows.push(cloneRow(draft));
		}
		state.pages[m.pageKey] = rows;
		markTouched(m.pageKey);
		closeModal();
	}

	function removeRow(pageKey, rowUid) {
		state.pages[pageKey] = (state.pages[pageKey] || []).filter(function (r) {
			return r._uid !== rowUid;
		});
		markTouched(pageKey);
		render();
	}

	function reorderWithinZone(pageKey, zoneKey, fromUid, toUid) {
		if (!fromUid || fromUid === toUid) {
			return;
		}
		const all = state.pages[pageKey] || [];
		const zoneRows = all.filter(function (r) {
			return r.placement_zone === zoneKey;
		});
		const fromIdx = zoneRows.findIndex(function (r) {
			return r._uid === fromUid;
		});
		const toIdx = zoneRows.findIndex(function (r) {
			return r._uid === toUid;
		});
		if (fromIdx === -1 || toIdx === -1) {
			return;
		}
		const moved = zoneRows.splice(fromIdx, 1)[0];
		zoneRows.splice(toIdx, 0, moved);

		const rebuilt = [];
		const zoneQueues = {};
		ZONE_ORDER.forEach(function (z) {
			zoneQueues[z] = all.filter(function (r) {
				return r.placement_zone === z;
			});
		});
		zoneQueues[zoneKey] = zoneRows;

		ZONE_ORDER.forEach(function (z) {
			(zoneQueues[z] || []).forEach(function (r) {
				rebuilt.push(r);
			});
		});
		all.forEach(function (r) {
			if (ZONE_ORDER.indexOf(r.placement_zone) === -1) {
				rebuilt.push(r);
			}
		});

		state.pages[pageKey] = rebuilt;
		markTouched(pageKey);
		render();
	}

	function resetPage(pageKey) {
		state.pages[pageKey] = cloneDefaults(pageKey);
		state.useManifestDefault.add(pageKey);
		state.touchedPages.delete(pageKey);
		state.dirty = true;
		render();
	}

	function resetAll() {
		if (!window.confirm(i18n.confirmResetAll || 'پاک شود؟')) {
			return;
		}
		saveToServer([]);
	}

	function saveLayout() {
		const payload = serializePlacements();
		const placements = payload.placements;
		const clearPages = payload.clearPages || [];

		if (placements.length === 0 && clearPages.length === 0) {
			if (state.dirty) {
				setMessage(i18n.error || 'خطا در ذخیره', 'warn');
			} else {
				setMessage(i18n.nothingToSave || 'تغییری برای ذخیره نیست', 'info');
			}
			return;
		}
		saveToServer(placements, clearPages);
	}

	function saveToServer(placements, clearPages) {
		clearPages = clearPages || [];
		state.saving = true;
		render();

		const body = new FormData();
		body.append('action', 'ak_save_layout');
		body.append('nonce', cfg.nonce);
		body.append('placements', JSON.stringify(placements));
		body.append('clear_pages', JSON.stringify(clearPages));

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (!json.success) {
					setMessage(i18n.error || 'خطا', 'warn');
					return;
				}

				if (!cfg.placements) {
					cfg.placements = {};
				}

				const savedByPage = {};
				placements.forEach(function (row) {
					const pk = row.placement_page;
					if (!savedByPage[pk]) {
						savedByPage[pk] = [];
					}
					savedByPage[pk].push(row);
				});

				Object.keys(savedByPage).forEach(function (pageKey) {
					cfg.placements[pageKey] = savedByPage[pageKey];
				});

				clearPages.forEach(function (pageKey) {
					delete cfg.placements[pageKey];
				});

				cfg.hasCustom = Object.keys(cfg.placements).length > 0;

				state.touchedPages.clear();
				state.useManifestDefault.clear();
				state.dirty = false;
				setMessage(i18n.saved || 'ذخیره شد', 'success');
			})
			.catch(function () {
				setMessage(i18n.error || 'خطا', 'warn');
			})
			.finally(function () {
				state.saving = false;
				render();
			});
	}

	function statusBanner() {
		if (state.message) {
			return el('div', {
				className: 'ak-lb-status ak-lb-status--' + state.messageType,
				text: state.message,
			});
		}
		if (state.dirty) {
			return el('div', {
				className: 'ak-lb-status ak-lb-status--warn',
				text: i18n.unsavedChanges || 'تغییرات ذخیره نشده',
			});
		}
		const pageKey = state.currentPage;
		const pageCustom = shouldPersistPage(pageKey) && !state.useManifestDefault.has(pageKey);
		const text = pageCustom || cfg.hasCustom
			? (i18n.customActive || 'سفارشی')
			: (i18n.usingDefaults || 'پیش‌فرض');
		return el('div', { className: 'ak-lb-status ak-lb-status--info', text: text });
	}

	function render() {
		const root = document.getElementById('ak-layout-builder-app');
		if (!root) {
			return;
		}

		root.innerHTML = '';

		const tabs = el('div', { className: 'ak-lb-page-tabs' });
		Object.keys(cfg.pages).forEach(function (pageKey) {
			const tab = el('button', {
				type: 'button',
				className: 'ak-lb-tab' + (state.currentPage === pageKey ? ' is-active' : ''),
				text: cfg.pages[pageKey].label || pageKey,
				onclick: function () {
					state.currentPage = pageKey;
					render();
				},
			});
			tabs.appendChild(tab);
		});

		const saveBtn = el('button', {
			type: 'button',
			className: 'ak-lb-btn ak-lb-btn--primary' + (state.dirty ? ' ak-lb-btn--dirty' : ''),
			disabled: state.saving ? 'disabled' : null,
			onclick: saveLayout,
		});
		if (state.saving) {
			saveBtn.appendChild(el('span', { className: 'ak-lb-saving' }));
		}
		saveBtn.appendChild(document.createTextNode(' ' + (i18n.save || 'ذخیره')));

		const toolbar = el('div', { className: 'ak-lb-toolbar' }, [
			el('div', { className: 'ak-lb-toolbar-meta' }, [
				el('span', { html: 'ظاهر فعال: <strong>' + esc(cfg.appearanceLabel || cfg.appearanceId) + '</strong>' }),
			]),
			tabs,
			el('div', { className: 'ak-lb-actions' }, [
				saveBtn,
				el('button', {
					type: 'button',
					className: 'ak-lb-btn',
					text: i18n.resetPage || 'بازگشت پیش‌فرض',
					onclick: function () {
						resetPage(state.currentPage);
					},
				}),
				el('button', {
					type: 'button',
					className: 'ak-lb-btn ak-lb-btn--danger',
					text: i18n.resetAll || 'پاک همه',
					onclick: resetAll,
				}),
			]),
		]);

		root.appendChild(toolbar);
		root.appendChild(statusBanner());
		root.appendChild(renderCanvas(state.currentPage));

		const modal = renderModal();
		if (modal) {
			root.appendChild(modal);
		}
	}

	initState();
	render();
})();
