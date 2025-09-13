/**
 * WAWP – Block-list manager
 * tagify + intl-tel-input + instant-save
 * ----------------------------------------------------------
 * Requires:
 *   – Tagify (window.Tagify)
 *   – intl-tel-input (window.intlTelInput)
 *   – awpBlockAjax = { ajaxUrl, nonce } (localised in PHP)
 */
document.addEventListener('DOMContentLoaded', () => {

	/* ───────────────────────── Tagify ───────────────────────── */
	const textarea   = document.querySelector('.awp-block-area');   // hidden <textarea>
	const tagifyInput = document.getElementById('awp_block_tagify'); // visible Tagify field

	if (!textarea || !tagifyInput || typeof Tagify === 'undefined') {
		return; // bail – dependencies missing
	}

	const tagify = new Tagify(tagifyInput, {
		delimiters : ', ',
		pattern    : /^\d+$/,            // digits only
		dropdown   : { enabled : 0 }
	});

	// seed Tagify with the existing list from <textarea>
	tagify.addTags(
		textarea.value.split('\n').map(v => v.trim()).filter(Boolean)
	);

	// whenever Tagify changes → sync back to <textarea>
	tagify.on('change', () => {
		textarea.value = tagify.value.map(o => o.value).join('\n');
	});


	/* ─────────────── Intl-Tel-Input + “Add” button ─────────────── */
	const intlField = document.getElementById('awp_block_intl'); // <input type="tel">
	const addBtn    = document.getElementById('awp_block_add_btn');

	if (intlField && addBtn && typeof window.intlTelInput !== 'undefined') {

		const iti = window.intlTelInput(intlField, {
			separateDialCode : true,
			initialCountry   : 'auto',
			utilsScript      : 'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.8/build/js/utils.js',
			geoIpLookup      : cb => {
				// Tiny free service – ≈ 45 req/min
				fetch('https://ipapi.co/json/')
					.then(r => r.ok ? r.json() : {})
					.then(d => cb(d.country_code || 'us'))
					.catch(() => cb('us'));
			}
		});

		addBtn.addEventListener('click', () => {

			/* 1) basic validation */
			if ( ! iti.isValidNumber() ) {
				intlField.classList.add('awp-error');
				intlField.focus();
				return;
			}
			intlField.classList.remove('awp-error');

			/* 2) normalise → Tagify */
			const cleaned = iti.getNumber().replace(/^\+/, ''); // strip leading "+"
			tagify.addTags([ cleaned ]);    // duplicate numbers are ignored by Tagify
			tagify.trigger('change');       // force hidden <textarea> sync
			intlField.value = '';

			/* 3) instant Ajax save (same as clicking “Save Block List”) */
			const fd = new FormData();
			fd.append('action', 'awp_save_block_list');
			fd.append('nonce',  awpBlockAjax.nonce);
			fd.append('list',   textarea.value);  // whole list, \n-separated

			fetch(awpBlockAjax.ajaxUrl, { method : 'POST', body : fd })
				.then(r => r.json())
				.then(res => {
					if (res.success) {
						// tiny ✔ feedback
						addBtn.dataset.orig = addBtn.dataset.orig || addBtn.textContent;
						addBtn.textContent = '✓';
						setTimeout(() => addBtn.textContent = addBtn.dataset.orig, 900);
					} else {
						console.error('Block-list save failed:', res);
					}
				})
				.catch(err => console.error('Ajax error:', err));
		});
	}

});
