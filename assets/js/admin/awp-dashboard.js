function handleWooParentToggle(checkbox) {
  handleToggle(checkbox);
  const isChecked = checkbox.checked;
  const childSelectors = [
    '[data-option="awp_woo_notification_enabled"]',
    '[data-option="awp_admin_notification_enabled"]',
    '[data-option="awp_followup_messages_enabled"]'
  ];
  childSelectors.forEach(sel => {
    const child = document.querySelector(sel);
    if (child) {
      child.disabled = !isChecked;
    }
  });
}

function handleOtpParentToggle(checkbox) {
  handleToggle(checkbox);
  const isChecked = checkbox.checked;
  const childSelectors = [
    '[data-option="awp_otp_login_enabled"]',
    '[data-option="awp_signup_enabled"]',
    '[data-option="awp_checkout_otp_enabled"]'
  ];
  childSelectors.forEach(sel => {
    const child = document.querySelector(sel);
    if (child) {
      child.disabled = !isChecked;
    }
  });
}

function handleToggle(checkbox) {
  const optionName = checkbox.getAttribute('data-option');
  const isEnabled = checkbox.checked ? 1 : 0;
  const nonce = document.getElementById('awp_live_toggle_nonce').value;
  const formData = new FormData();
  formData.append('action', 'awp_save_toggle');
  formData.append('option_name', optionName);
  formData.append('option_value', isEnabled);
  formData.append('_ajax_nonce', nonce);

  // Get the label text for popup title
const cardTitle = checkbox.closest('.awp-toggle-group')?.querySelector('.card-title');
const optionTitle = cardTitle ? cardTitle.innerText.trim() : optionName;


  fetch(awpVars.ajax_url, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        showPopup(
          `${awpPopupText.errorPrefix}${data.data || 'Unknown error'}`,
          true,
          optionTitle
        );
      } else {
        const statusText = isEnabled ? awpPopupText.enabledLabel : awpPopupText.disabledLabel;
        showPopup(`${awpPopupText.statusLabel} ${statusText}`, false, optionTitle);
      }
    })
    .catch(err => {
      showPopup(`${awpPopupText.requestFailed}${err}`, true, optionTitle);
    });
}

function awpToggleIconVisibility(checkbox) {
  const icon = checkbox.closest('.awp-toggle-group')?.querySelector('.awp-setting-icon');
  if (icon) {
    icon.style.display = checkbox.checked ? 'flex' : 'none';
  }
}


function showPopup(message, isError, title = '') {
  const overlay = document.createElement('div');
  overlay.className = 'awp-overlay';
  overlay.style.position = 'fixed';
  overlay.style.top = 0;
  overlay.style.left = 0;
  overlay.style.width = '100vw';
  overlay.style.height = '100vh';
  overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
  overlay.style.display = 'flex';
  overlay.style.alignItems = 'center';
  overlay.style.justifyContent = 'center';
  overlay.style.zIndex = 9999;

  const popup = document.createElement('div');
  popup.className = 'awp-popup';
  popup.style.background = '#fff';
  popup.style.padding = '24px';
  popup.style.borderRadius = '12px';
  popup.style.maxWidth = '400px';
  popup.style.width = '100%';
  popup.style.boxShadow = '0 10px 20px rgba(0,0,0,0.2)';
  popup.style.textAlign = 'center';

  const img = document.createElement('img');
  img.src = isError ? awpPopupText.errorGif : awpPopupText.successGif;
  img.alt = isError ? 'Error' : 'Success';
  img.style.width = '60px';
  img.style.marginBottom = '16px';

  const heading = document.createElement('h3');
  heading.textContent = title;
  heading.style.marginBottom = '8px';
  heading.style.color = '#333';
  heading.style.fontSize = '20px';

  const msgP = document.createElement('p');
  msgP.textContent = message;
  msgP.className = isError ? 'awp-error' : 'awp-success';
  msgP.style.color = isError ? '#cc0000' : '#28a745';
  msgP.style.fontSize = '16px';
  msgP.style.marginBottom = '20px';

  const closeBtn = document.createElement('button');
  closeBtn.textContent = 'OK';
  closeBtn.className = 'awp-btn primary';
  closeBtn.addEventListener('click', function () {
    document.body.removeChild(overlay);
    window.location.reload();
  });

  popup.appendChild(img);
  if (title) popup.appendChild(heading);
  popup.appendChild(msgP);
  popup.appendChild(closeBtn);
  overlay.appendChild(popup);
  document.body.appendChild(overlay);
}


