/**
 * --- VARIABLES GLOBALES ---
 */

let calendar;
const boutonDemande = document.querySelector(".menu header .button");
const modalDemande = document.querySelector(".demande-container");
const btnFermer = document.querySelector(".close-icon");
const btnAnnuler = document.querySelector(".btn-cancel");
const overlay = document.getElementById("overlay");

/**
 * --- SYSTÈME DE TOASTS ---
 */
function showToast(message, type = "success") {
  let container =
    document.querySelector(".toast-container") ||
    Object.assign(document.createElement("div"), {
      className: "toast-container",
    });
  if (!container.parentNode) document.body.appendChild(container);

  const toast = document.createElement("div");
  toast.className = `custom-toast ${type}`;
  const icon =
    type === "success"
      ? "check-circle"
      : type === "danger"
        ? "alert-octagon"
        : "info";
  const title =
    type === "success"
      ? "Succès"
      : type === "danger"
        ? "Attention"
        : "Information";

  toast.innerHTML = `
    <i data-lucide="${icon}" style="color:inherit"></i>
    <div class="toast-content">
      <span class="toast-title">${title}</span>
      <span class="toast-message">${message}</span>
    </div>`;

  container.appendChild(toast);
  if (window.lucide) lucide.createIcons();
  setTimeout(() => toast.classList.add("show"), 100);
  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 500);
  }, 2500);
}

/**
 * --- MODALE DE CONFIRMATION ---
 */
function customConfirm(title, text, type = "info") {
  return new Promise((resolve) => {
    const modal = document.getElementById("custom-confirm-modal");
    if (!modal) return resolve(confirm(text));

    document.getElementById("modal-confirm-title").innerText = title;
    document.getElementById("modal-confirm-text").innerText = text;
    const iconContainer = document.getElementById("modal-confirm-icon");
    const btnCancel = document.getElementById("modal-confirm-cancel");
    const btnProceed = document.getElementById("modal-confirm-proceed");

    modal.style.display = "flex";
    iconContainer.className =
      "modal-confirm-icon " +
      (type === "danger" ? "danger" : type === "success" ? "success" : "");
    btnProceed.style.backgroundColor =
      type === "danger"
        ? "#ef4444"
        : type === "success"
          ? "#10b981"
          : "#3f81ea";

    btnCancel.onclick = () => {
      modal.style.display = "none";
      resolve(false);
    };
    btnProceed.onclick = () => {
      modal.style.display = "none";
      resolve(true);
    };
  });
}

/**
 * --- FILTRAGE DU TABLEAU ---
 */
function filtrerTableau() {
  const tableBody = document.getElementById("tableBody");
  if (!tableBody) return;
  const rows = tableBody.querySelectorAll("tr");

  const fType =
    document.getElementById("filterType")?.value.toLowerCase() || "";
  const fStatut =
    document.getElementById("filterStatut")?.value.toLowerCase() || "";
  const fDateDebut = document.getElementById("filterDateDebut")?.value || "";
  const fDateFin = document.getElementById("filterDateFin")?.value || "";

  rows.forEach((row) => {
    if (row.cells.length < 3) return;
    const rType = (row.dataset.type || "").toLowerCase();
    const rStatut = (row.dataset.statut || "").toLowerCase();
    const rDebut = row.dataset.debut || "";
    const rFin = row.dataset.fin || "";

    const matchType = fType === "" || rType.includes(fType);
    const matchStatut = fStatut === "" || rStatut.includes(fStatut);

    let matchDate = true;
    if (fDateDebut && rFin < fDateDebut) matchDate = false;
    if (fDateFin && rDebut > fDateFin) matchDate = false;

    row.style.display = matchType && matchStatut && matchDate ? "" : "none";
  });
}

/**
 * --- OUVERTURE MODALE TRAÇABILITÉ ---
 * Séparée ici au niveau racine pour éviter tout conflit de scope
 */
function ouvrirTraceModif(btn) {
  let data;
  try {
    data = JSON.parse(btn.getAttribute("data-modif"));
  } catch (err) {
    showToast("Erreur de lecture des données", "danger");
    return;
  }

  const modal = document.getElementById("modal-trace-modif");
  if (!modal) {
    showToast("Modale introuvable dans le DOM", "danger");
    return;
  }

  const isSuppr = data.type_action === "Suppression";

  document.getElementById("trace-title").innerText = isSuppr
    ? "Demande d'annulation en cours"
    : "Demande de modification en cours";

  document.getElementById("trace-badge-zone").innerHTML = `
    <span style="font-size:11px;padding:3px 12px;border-radius:20px;font-weight:600;
          border:1px solid currentColor;
          ${isSuppr ? "background:#fee2e2;color:#b91c1c;" : "background:#fef3c7;color:#92400e;"}">
      ${isSuppr ? "Annulation demandée" : "Modification demandée"}
    </span>
    <span style="font-size:11px;color:#9ca3af;margin-left:8px;">En attente de validation</span>
  `;

  document.getElementById("trace-avant").innerText =
    `${data.type_conge} · ${data.date_debut} → ${data.date_fin}`;

  if (isSuppr) {
    document.getElementById("trace-apres-label").innerText = "Après annulation";
    document.getElementById("trace-apres").innerText = "Supprimé";
    document.getElementById("trace-apres").style.color = "#dc2626";
  } else {
    document.getElementById("trace-apres-label").innerText =
      "Nouvelles dates souhaitées";
    const newType = data.nouveau_type || data.type_conge;
    const newDebut = data.nouvelle_date_debut || "—";
    const newFin = data.nouvelle_date_fin || "—";
    document.getElementById("trace-apres").innerText =
      `${newType} · ${newDebut} → ${newFin}`;
    document.getElementById("trace-apres").style.color = "#059669";
  }

  document.getElementById("trace-raison").innerText = `"${data.raison}"`;
  document.getElementById("trace-date").innerText = data.created_at;

  // On force le display sans passer par les écouteurs globaux
  modal.style.setProperty("display", "flex", "important");
  if (window.lucide) lucide.createIcons();
}

function fermerTraceModif() {
  const modal = document.getElementById("modal-trace-modif");
  if (modal) modal.style.setProperty("display", "none", "important");
}

/**
 * --- INITIALISATION DOM ---
 */
document.addEventListener("DOMContentLoaded", function () {
  console.log("DayOff Engine chargé !");

  // 1. REFRESH VALIDATIONS
  document
    .getElementById("btn-refresh-validations")
    ?.addEventListener("click", function () {
      this.querySelector("i")?.classList.add("rotating");
      showToast("Actualisation...", "info");
      setTimeout(() => location.reload(), 800);
    });

  // 2. MODALE DEMANDE — ouverture / fermeture
  const modaleDemande = document.getElementById("modale-demande");
  const overlayDemande = document.getElementById("overlay");
  const btnOuvrir =
    document.getElementById("btn-ouvrir-demande") ||
    document.querySelector(".menu header .button");

  btnOuvrir?.addEventListener("click", () => {
    if (modaleDemande) modaleDemande.style.display = "block";
    if (overlayDemande) overlayDemande.style.display = "block";
  });

  document.addEventListener("click", function (e) {
    // On ne traite pas les clics qui viennent de la modale trace
    if (e.target.closest("#modal-trace-modif")) return;
    // On ne traite pas les clics sur les boutons qui ouvrent la modale trace
    if (e.target.closest(".btn-voir-modif")) return;

    const isCroix = e.target.closest(".close-icon");
    const isAnnuler = e.target.closest(".btn-cancel");
    const isOverlay = e.target === overlayDemande;

    if (isCroix || isAnnuler || isOverlay) {
      if (modaleDemande) modaleDemande.style.display = "none";
      if (overlayDemande) overlayDemande.style.display = "none";
    }
  });

  // 3. ONGLETS
  const menuItems = document.querySelectorAll(".menu-item");
  const tabContents = document.querySelectorAll(".tab-content");

  function switchTab(id) {
    const targetSection = document.getElementById(id);
    if (!targetSection) return;

    menuItems.forEach((m) =>
      m.classList.toggle("active", m.getAttribute("data-target") === id),
    );
    tabContents.forEach((section) => {
      if (section.id === id) {
        section.style.setProperty("display", "block", "important");
        section.classList.add("active");
      } else {
        section.style.setProperty("display", "none", "important");
        section.classList.remove("active");
      }
    });

    localStorage.setItem("activeTab", id);
    if (id === "calendrier" && calendar)
      setTimeout(() => calendar.updateSize(), 100);
  }

  menuItems.forEach((link) => {
    link.addEventListener("click", function (e) {
      const targetId = this.getAttribute("data-target");
      if (!targetId || targetId === "#") return;
      e.preventDefault();
      switchTab(targetId);
    });
  });

  const urlParams = new URLSearchParams(window.location.search);
  const tabParam = urlParams.get("tab");
  const tabToOpen =
    tabParam || localStorage.getItem("activeTab") || "dashboard";
  switchTab(tabToOpen);

  if (tabParam) {
    window.history.replaceState({}, document.title, window.location.pathname);
  }

  // 4. CONFIGURATION GLOBALE
  document
    .getElementById("form-config-global")
    ?.addEventListener("submit", async function (e) {
      e.preventDefault();
      if (
        await customConfirm(
          "Appliquer les changements ?",
          "Les nouvelles règles s'appliqueront immédiatement.",
        )
      ) {
        const formData = new FormData(this);
        formData.append("action", "save_global_config");
        const btn = this.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        fetch(dayoff_ajax_url, { method: "POST", body: formData })
          .then((res) => res.json())
          .then((data) => {
            if (data.success) {
              showToast("Configuration mise à jour !", "success");
              setTimeout(() => location.reload(), 1000);
            } else {
              showToast("Erreur : " + data.data, "danger");
              if (btn) btn.disabled = false;
            }
          });
      }
    });

  // 5. FILTRES
  ["filterType", "filterStatut", "filterDateDebut", "filterDateFin"].forEach(
    (id) => {
      document.getElementById(id)?.addEventListener("input", filtrerTableau);
    },
  );

  document
    .getElementById("btn-reset-filters")
    ?.addEventListener("click", () => {
      [
        "filterType",
        "filterStatut",
        "filterDateDebut",
        "filterDateFin",
      ].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
      });
      filtrerTableau();
      showToast("Filtres réinitialisés", "info");
    });

  // 6. SOUMISSION CONGÉ
  document
    .getElementById("form-conge")
    ?.addEventListener("submit", function (e) {
      e.preventDefault();
      const btn = this.querySelector('button[type="submit"]');
      const originalText = btn.innerText;
      btn.innerText = "Vérification...";
      btn.disabled = true;

      const formData = new FormData(this);
      formData.append("action", "submit_conge");

      fetch(dayoff_ajax_url, { method: "POST", body: formData })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            showToast(data.data, "success");
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast(data.data, "danger");
            btn.innerText = originalText;
            btn.disabled = false;
          }
        })
        .catch(() => {
          showToast("Erreur de communication", "danger");
          btn.innerText = originalText;
          btn.disabled = false;
        });
    });

  // 7. GESTION ADMIN UTILISATEURS
  const globalUserForm = document.getElementById("form-admin-user-global");

  document.addEventListener("click", function (e) {
    const btnEdit = e.target.closest(".btn-edit-user");
    if (!btnEdit) return;

    const userData = JSON.parse(btnEdit.getAttribute("data-user"));
    const editPanel = document.getElementById("edit-panel");

    if (editPanel) {
      editPanel.querySelector(".sidebar-placeholder").style.display = "none";
      editPanel.querySelector(".sidebar-content").style.display = "block";
    }

    document.getElementById("panel-title").innerText =
      "Modifier " + userData.name;
    document.getElementById("form-mode").value = "update";
    document.getElementById("edit-user-id").value = userData.id;
    document.getElementById("edit-display-name").value = userData.name;
    document.getElementById("edit-email").value = userData.email;
    document.getElementById("edit-cp").value = userData.cp;
    document.getElementById("edit-rtt").value = userData.rtt;
    document.getElementById("password-field-container").style.display = "none";

    const btnReset = document.getElementById("btn-reset-history");
    if (btnReset) btnReset.style.display = "block";

    fetchUserHistory(userData.id);
  });

  document
    .getElementById("btn-reset-history")
    ?.addEventListener("click", async function () {
      const userId = document.getElementById("edit-user-id").value;
      const userName = document.getElementById("edit-display-name").value;
      if (!userId) return;

      const confirmed = await customConfirm(
        "Réinitialiser ?",
        `Voulez-vous supprimer TOUT l'historique de ${userName} ? Cette action est irréversible.`,
        "danger",
      );

      if (confirmed) {
        const fd = new FormData();
        fd.append("action", "reset_user_history");
        fd.append("target_user_id", userId);

        fetch(dayoff_ajax_url, { method: "POST", body: fd })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              showToast(data.data, "success");
              const listContainer = document.getElementById("user-leave-list");
              if (listContainer)
                listContainer.innerHTML =
                  "<li class='text-muted small'>Historique vidé.</li>";

              if (calendar) {
                calendar.getEvents().forEach((event) => {
                  if (event.title.startsWith(userName)) event.remove();
                });
              }
            } else {
              showToast(data.data, "danger");
            }
          })
          .catch(() =>
            showToast("Erreur lors de la réinitialisation", "danger"),
          );
      }
    });

  // Bouton "Nouveau Collaborateur"
  const btnOpenAdd = document.getElementById("btn-open-add-user");
  const editPanel = document.getElementById("edit-panel");

  if (btnOpenAdd && editPanel && globalUserForm) {
    btnOpenAdd.addEventListener("click", () => {
      editPanel.querySelector(".sidebar-placeholder").style.display = "none";
      editPanel.querySelector(".sidebar-content").style.display = "block";

      globalUserForm.reset();
      document.getElementById("panel-title").innerText =
        "Nouveau Collaborateur";
      document.getElementById("form-mode").value = "create";
      document.getElementById("edit-user-id").value = "";

      const passField = document.getElementById("password-field-container");
      if (passField) passField.style.display = "block";

      const listContainer = document.getElementById("user-leave-list");
      if (listContainer)
        listContainer.innerHTML =
          "<li class='text-muted small'>Nouvel utilisateur</li>";
    });
  }

  globalUserForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const mode = document.getElementById("form-mode").value;
    const actionName =
      mode === "create" ? "add_new_user_admin" : "update_user_soldes";

    if (
      await customConfirm(
        "Confirmation",
        "Voulez-vous valider cette opération ?",
      )
    ) {
      const formData = new FormData(globalUserForm);
      formData.append("action", actionName);

      fetch(dayoff_ajax_url, { method: "POST", body: formData })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            showToast(data.data || "Opération réussie", "success");
            setTimeout(() => location.reload(), 1500);
          } else {
            showToast(data.data || "Une erreur est survenue", "danger");
          }
        })
        .catch((err) => {
          console.error(err);
          showToast("Erreur de communication avec le serveur", "danger");
        });
    }
  });

  // 8. DURÉE DYNAMIQUE
  ["date_debut", "date_fin"].forEach((id) => {
    document.getElementById(id)?.addEventListener("change", () => {
      const d1 = document.getElementById("date_debut")?.value;
      const d2 = document.getElementById("date_fin")?.value;
      if (d1 && d2) {
        const duree = calculerDureeConges(d1, d2);
        const infoDiv = document.getElementById("duree-info");
        if (infoDiv) {
          if (duree > 0) {
            document.getElementById("calcul-jours").innerText = duree;
            infoDiv.style.display = "block";
          } else {
            infoDiv.style.display = "none";
          }
        }
      }
    });
  });

  // 9. CALENDRIER
  const calendarEl = document.getElementById("calendar");
  if (calendarEl && typeof FullCalendar !== "undefined") {
    let selectedStart = null;

    calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: "dayGridMonth",
      locale: "fr",
      selectable: false,
      events:
        typeof congesEvents !== "undefined" && Array.isArray(congesEvents)
          ? congesEvents
          : [],

      dateClick: function (info) {
        const inD = document.querySelector('input[name="date_debut"]');
        const inF = document.querySelector('input[name="date_fin"]');

        if (!selectedStart) {
          selectedStart = info.dateStr;
          if (inD) inD.value = selectedStart;
          if (inF) inF.value = "";
          document
            .querySelectorAll(".fc-day")
            .forEach((d) => d.classList.remove("fc-day-selected-start"));
          info.dayEl.classList.add("fc-day-selected-start");
        } else {
          let start = selectedStart;
          let end = info.dateStr;
          if (end < start) [start, end] = [end, start];
          if (inD) inD.value = start;
          if (inF) inF.value = end;
          selectedStart = null;
          document
            .querySelectorAll(".fc-day")
            .forEach((d) => d.classList.remove("fc-day-selected-start"));
          if (modalDemande) modalDemande.style.display = "block";
          if (overlay) overlay.style.display = "block";
        }
      },
    });
    calendar.render();
  }

  if (window.lucide) lucide.createIcons();

  // 10. GESTION DEMI-JOURNÉES
  const selectMoment = document.getElementById("moment_journee");
  const containerDateFin = document.getElementById("container-date-fin");
  const inputDateDebut = document.getElementById("date_debut");
  const inputDateFin = document.getElementById("date_fin");
  const labelDebut = document.getElementById("label-date-debut");

  if (selectMoment) {
    selectMoment.addEventListener("change", function () {
      if (this.value !== "full") {
        if (containerDateFin) containerDateFin.style.display = "none";
        if (inputDateFin) inputDateFin.removeAttribute("required");
        if (labelDebut) labelDebut.innerText = "Date du congé";
        if (inputDateFin && inputDateDebut)
          inputDateFin.value = inputDateDebut.value;
      } else {
        if (containerDateFin) containerDateFin.style.display = "block";
        if (inputDateFin) inputDateFin.setAttribute("required", "required");
        if (labelDebut) labelDebut.innerText = "Date de début";
      }
    });

    inputDateDebut?.addEventListener("change", function () {
      if (selectMoment.value !== "full" && inputDateFin) {
        inputDateFin.value = this.value;
      }
    });
  }

  // 11. RECHERCHE UTILISATEURS
  document
    .getElementById("user-search")
    ?.addEventListener("input", function () {
      const val = this.value.toLowerCase();
      document.querySelectorAll(".user-row-item[data-name]").forEach((row) => {
        row.style.display = row.dataset.name.includes(val) ? "" : "none";
      });
    });

  // 12. FILTRAGE VALIDATIONS ADMIN
  function filtrerValidationsAdmin() {
    const rows = document.querySelectorAll("#validation_conges .user-row-item");
    const searchName =
      document.getElementById("adminFilterName")?.value.toLowerCase() || "";
    const filterType = document.getElementById("adminFilterType")?.value || "";
    const filterDate = document.getElementById("adminFilterDate")?.value || "";

    rows.forEach((row) => {
      const userName =
        row.querySelector(".user-name-label")?.innerText.toLowerCase() || "";
      const congeType = row.querySelector(".user-email-sub")?.innerText || "";
      const matchName = userName.includes(searchName);
      const matchType = filterType === "" || congeType.includes(filterType);
      const matchDate = filterDate === "" || row.dataset.debut === filterDate;

      row.style.display = matchName && matchType && matchDate ? "grid" : "none";
    });
  }

  ["adminFilterName", "adminFilterType", "adminFilterDate"].forEach((id) => {
    document
      .getElementById(id)
      ?.addEventListener("input", filtrerValidationsAdmin);
  });

  document
    .getElementById("btn-reset-admin-filters")
    ?.addEventListener("click", () => {
      ["adminFilterName", "adminFilterType", "adminFilterDate"].forEach(
        (id) => {
          const el = document.getElementById(id);
          if (el) el.value = "";
        },
      );
      filtrerValidationsAdmin();
    });

  // 13. ÉCOUTEUR BOUTON FERMER PANNEAU LATÉRAL
  document.addEventListener("click", function (e) {
    if (e.target.closest("#btn-close-edit-panel")) {
      const editPanel = document.getElementById("edit-panel");
      if (editPanel) {
        editPanel.querySelector(".sidebar-placeholder").style.display = "flex";
        editPanel.querySelector(".sidebar-content").style.display = "none";
        document.getElementById("form-admin-user-global")?.reset();
      }
    }
  });

  // 14. ÉCOUTEUR BOUTON "MODIF EN ATTENTE" — avec stopPropagation
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".btn-voir-modif");
    if (!btn) return;
    e.stopPropagation(); // empêche la propagation vers l'écouteur qui ferme les modales
    ouvrirTraceModif(btn);
  });

  // 15. ÉCOUTEUR BOUTON "REFAIRE UNE DEMANDE"
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".btn-refaire-demande");
    if (!btn) return;
    e.stopPropagation();

    const type = btn.getAttribute("data-type");
    const debut = btn.getAttribute("data-debut"); // format Y-m-d
    const fin = btn.getAttribute("data-fin"); // format Y-m-d

    // Pré-remplissage de la modale de dépôt
    const selectType = document.getElementById("type_conge");
    const inputDebut = document.getElementById("date_debut");
    const inputFin = document.getElementById("date_fin");
    const modaleDemande = document.getElementById("modale-demande");
    const overlayDemande = document.getElementById("overlay");

    if (selectType) {
      // On sélectionne la bonne option dans le select
      Array.from(selectType.options).forEach((opt) => {
        opt.selected = opt.value === type;
      });
    }
    if (inputDebut) inputDebut.value = debut;
    if (inputFin) inputFin.value = fin;

    // Afficher la demi-journée si nécessaire (reset à "full" par défaut)
    const selectMoment = document.getElementById("moment_journee");
    if (selectMoment) selectMoment.value = "full";

    const containerDateFin = document.getElementById("container-date-fin");
    if (containerDateFin) containerDateFin.style.display = "block";

    // Ouvrir la modale
    if (modaleDemande) modaleDemande.style.display = "block";
    if (overlayDemande) overlayDemande.style.display = "block";

    // Rafraîchir les icônes Lucide dans la modale
    if (window.lucide) lucide.createIcons();
  });

  // REFRESH MODIFICATIONS
  document
    .getElementById("btn-refresh-modifications")
    ?.addEventListener("click", function () {
      this.querySelector("i")?.classList.add("rotating");
      showToast("Actualisation...", "info");
      setTimeout(() => location.reload(), 800);
    });
}); // fin DOMContentLoaded

/**
 * --- FONCTIONS GLOBALES ---
 */

function fetchUserHistory(userId) {
  const listContainer = document.getElementById("user-leave-list");
  const loader = document.getElementById("history-loader");
  if (!listContainer) return;

  listContainer.innerHTML = "";
  if (loader) loader.style.display = "block";

  const fd = new FormData();
  fd.append("action", "get_user_leave_history");
  fd.append("target_user_id", userId);

  fetch(dayoff_ajax_url, { method: "POST", body: fd })
    .then((r) => r.json())
    .then((data) => {
      if (loader) loader.style.display = "none";
      if (data.success) {
        data.data.forEach((item) => {
          const li = document.createElement("li");
          li.className =
            "list-group-item d-flex justify-content-between align-items-center px-0 py-2 bg-transparent";
          const d1 = new Date(item.date_debut).toLocaleDateString("fr-FR");
          const d2 = new Date(item.date_fin).toLocaleDateString("fr-FR");
          li.innerHTML = `
            <div><span class="fw-bold">${item.type_conge}</span><br>
            <small class="text-muted">Du ${d1} au ${d2}</small></div>
            <span class="badge ${item.statut === "Validé" ? "bg-success-subtle text-success" : "bg-warning-subtle text-warning"} border">${item.statut}</span>`;
          listContainer.appendChild(li);
        });
      } else {
        listContainer.innerHTML = `<li class="text-muted italic small">Aucun historique.</li>`;
      }
    });
}

async function validerDemande(id, decision) {
  const type = decision === "Validé" ? "success" : "danger";
  if (
    await customConfirm(
      `${decision} ?`,
      `Confirmer le statut "${decision}" ?`,
      type,
    )
  ) {
    const row = document.querySelector(`.user-row-item[data-id="${id}"]`);
    const fd = new FormData();
    fd.append("action", "traiter_demande_admin");
    fd.append("demande_id", id);
    fd.append("decision", decision);
    fd.append(
      "commentaire_admin",
      row?.querySelector(".comm-admin")?.value || "",
    );

    fetch(dayoff_ajax_url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          showToast(`Demande ${decision}e`, type);
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(data.data || "Erreur", "danger");
        }
      });
  }
}

async function supprimerCollaborateur(id, name) {
  if (
    await customConfirm(
      "Supprimer ?",
      `Retirer ${name} définitivement ?`,
      "danger",
    )
  ) {
    const fd = new FormData();
    fd.append("action", "delete_user_admin");
    fd.append("target_user_id", id);
    fetch(dayoff_ajax_url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          showToast("Supprimé", "success");
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(data.data || "Erreur", "danger");
        }
      });
  }
}

async function annulerMaDemande(id) {
  if (await customConfirm("Annuler ?", "Supprimer cette demande ?", "danger")) {
    const fd = new FormData();
    fd.append("action", "delete_conge_user");
    fd.append("demande_id", id);
    fetch(dayoff_ajax_url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          showToast("Annulé", "success");
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(data.data || "Erreur", "danger");
        }
      });
  }
}

function calculerDureeConges(dateDebut, dateFin, startPeriod, endPeriod) {
  const d1 = new Date(dateDebut);
  const d2 = new Date(dateFin);
  if (d1 > d2) return 0;
  let diffDays = Math.ceil(Math.abs(d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
  if (startPeriod === "afternoon") diffDays -= 0.5;
  if (endPeriod === "noon") diffDays -= 0.5;
  return diffDays;
}

function traiterModif(id, decision) {
  const fd = new FormData();
  fd.append("action", "traiter_modification_admin");
  fd.append("modif_id", id);
  fd.append("decision", decision);

  fetch(dayoff_ajax_url, { method: "POST", body: fd })
    .then((r) => r.json())
    .then((data) => {
      showToast(data.data, "success");
      location.reload();
    });
}

let currentDemandeId = null;

function demanderModification(demandeId) {
  currentDemandeId = demandeId;
  document.getElementById("request-demande-id").value = demandeId;
  document.getElementById("modal-choix-modif").style.display = "flex";
  document.getElementById("form-request-change").style.display = "none";
}

function fermerModaleModif() {
  document.getElementById("modal-choix-modif").style.display = "none";
}

function afficherFormModif(type) {
  document.getElementById("request-type-action").value = type;
  document.getElementById("form-request-change").style.display = "block";

  const zoneModif = document.getElementById("zone-modif-dates");
  if (type === "Modification") {
    zoneModif.style.display = "block";
  } else {
    zoneModif.style.display = "none";
    zoneModif
      .querySelectorAll("input, select")
      .forEach((el) => (el.value = ""));
  }
}

function fermerTraceModif() {
  const modal = document.getElementById("modal-trace-modif");
  if (modal) modal.style.setProperty("display", "none", "important");
}

/**
 * --- EXPORT CSV ---
 */
function majZoneSelectionnes() {
  const checked = document.querySelectorAll(".user-export-checkbox:checked");
  const zone = document.getElementById("export-selected-zone");
  const chips = document.getElementById("export-selected-chips");
  const count = document.getElementById("export-selected-count");

  if (!zone || !chips || !count) return;

  if (checked.length === 0) {
    zone.style.setProperty("display", "none", "important");

    chips.innerHTML = "";
    return;
  }

  zone.style.setProperty("display", "block", "important");
  count.innerText = checked.length;
  chips.innerHTML = "";

  checked.forEach((cb) => {
    const name = cb.closest("label")?.querySelector("span")?.innerText || "?";
    const chip = document.createElement("div");
    chip.style.cssText =
      "display:inline-flex;align-items:center;gap:5px;background:#f0fdf4;color:#166534;border:1px solid #86efac;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:500;cursor:pointer;";
    chip.dataset.value = cb.value;
    chip.innerHTML = `${name} <span style="font-size:14px;line-height:1;color:#16a34a;">×</span>`;
    chips.appendChild(chip);
  });
}

// Clic sur un chip = décocher
document.addEventListener("click", function (e) {
  const chip = e.target.closest("#export-selected-chips > div");
  if (!chip) return;
  const val = chip.dataset.value;
  const cb = document.querySelector(`.user-export-checkbox[value="${val}"]`);
  if (cb) {
    cb.checked = false;
    const allCbs = document.querySelectorAll(".user-export-checkbox");
    const selectAll = document.getElementById("export-select-all");
    if (selectAll)
      selectAll.checked = Array.from(allCbs).every((c) => c.checked);
  }
  majZoneSelectionnes();
});

// Changement d'une checkbox = mise à jour chips
document.addEventListener("change", function (e) {
  if (!e.target.classList.contains("user-export-checkbox")) return;
  const allCbs = document.querySelectorAll(".user-export-checkbox");
  const selectAll = document.getElementById("export-select-all");
  if (selectAll) selectAll.checked = Array.from(allCbs).every((c) => c.checked);
  majZoneSelectionnes();
});

// Tout sélectionner / désélectionner
document
  .getElementById("export-select-all")
  ?.addEventListener("change", function () {
    document
      .querySelectorAll(".user-export-checkbox")
      .forEach((cb) => (cb.checked = this.checked));
    majZoneSelectionnes();
  });

// Ouverture de la modale
document.getElementById("btn-export-all")?.addEventListener("click", (e) => {
  e.preventDefault();
  const container = document.getElementById("export-users-list");
  const modal = document.getElementById("modal-export-custom");
  if (!container || !modal) return;

  container.innerHTML = "";

  let usersToSort = [];
  document.querySelectorAll(".user-row-item").forEach((row) => {
    const id = row.getAttribute("data-id");
    const name = row.querySelector(".user-name-label")?.innerText;
    if (id && name) usersToSort.push({ id, name });
  });

  usersToSort.sort((a, b) =>
    a.name.localeCompare(b.name, "fr", { sensitivity: "base" }),
  );

  usersToSort.forEach((user) => {
    const label = document.createElement("label");
    label.style.cssText =
      "display:flex;align-items:center;gap:10px;padding:8px;cursor:pointer;border-bottom:1px dashed var(--bordure);font-size:14px;";
    label.innerHTML = `<input type="checkbox" class="user-export-checkbox" value="${user.id}" checked> <span>${user.name}</span>`;
    container.appendChild(label);
  });

  // Reset recherche
  const searchInput = document.getElementById("search-export-user");
  if (searchInput) searchInput.value = "";

  // Reset select-all
  const selectAll = document.getElementById("export-select-all");
  if (selectAll) selectAll.checked = true;

  // ⚠️ Petit délai pour laisser le DOM se mettre à jour avant de lire les checkboxes
  setTimeout(() => majZoneSelectionnes(), 50);

  modal.style.display = "flex";
  if (window.lucide) lucide.createIcons();
});

// Recherche dans la liste
document
  .getElementById("search-export-user")
  ?.addEventListener("input", function () {
    const val = this.value.toLowerCase();
    document.querySelectorAll("#export-users-list label").forEach((item) => {
      const name = item.querySelector("span")?.innerText.toLowerCase() || "";
      item.style.display = name.includes(val) ? "flex" : "none";
    });
  });

// Génération du CSV
document
  .getElementById("btn-confirm-export-csv")
  ?.addEventListener("click", () => {
    const selected = document.querySelectorAll(".user-export-checkbox:checked");
    const ids = Array.from(selected).map((cb) => cb.value);

    if (ids.length === 0) {
      showToast("Sélectionnez au moins un collaborateur", "danger");
      return;
    }

    const form = document.createElement("form");
    form.method = "POST";
    form.action = dayoff_ajax_url;

    const actionInput = document.createElement("input");
    actionInput.type = "hidden";
    actionInput.name = "action";
    actionInput.value = "export_users_csv";
    form.appendChild(actionInput);

    const idsInput = document.createElement("input");
    idsInput.type = "hidden";
    idsInput.name = "group_ids";
    idsInput.value = ids.join(",");
    form.appendChild(idsInput);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    document.getElementById("modal-export-custom").style.display = "none";
    showToast("Génération du CSV en cours...", "success");
  });

// Export PDF — via URL directe (pas AJAX)
document
  .getElementById("btn-confirm-export-pdf")
  ?.addEventListener("click", () => {
    const selected = document.querySelectorAll(".user-export-checkbox:checked");
    const ids = Array.from(selected).map((cb) => cb.value);

    if (ids.length === 0) {
      showToast("Sélectionnez au moins un collaborateur", "danger");
      return;
    }

    // Construction de l'URL directe — pas besoin de formulaire POST
    const url =
      window.location.origin +
      window.location.pathname +
      "?dayoff_export_pdf=1&ids=" +
      ids.join(",");

    window.open(url, "_blank");

    document.getElementById("modal-export-custom").style.display = "none";
    showToast("Ouverture du rapport PDF...", "success");
  });

/**
 * FILTRES MODIFICATIONS ADMIN
 */
function filterModifications() {
  const nameSearch =
    document.getElementById("filterModifName")?.value.toLowerCase() || "";
  const actionSearch =
    document.getElementById("filterModifAction")?.value || "";
  const rows = document.querySelectorAll(".modif-row");

  rows.forEach((row) => {
    const userName = row.getAttribute("data-user") || "";
    const userAction = row.getAttribute("data-action") || "";
    const matchName = userName.includes(nameSearch);
    const matchAction = actionSearch === "" || userAction === actionSearch;

    if (matchName && matchAction) {
      row.style.setProperty("display", "grid", "important");
    } else {
      row.style.setProperty("display", "none", "important");
    }
  });
}

document
  .getElementById("filterModifName")
  ?.addEventListener("input", filterModifications);
document
  .getElementById("filterModifAction")
  ?.addEventListener("change", filterModifications);
document
  .getElementById("btn-reset-modif-filters")
  ?.addEventListener("click", () => {
    document.getElementById("filterModifName").value = "";
    document.getElementById("filterModifAction").value = "";
    filterModifications();
  });

/**
 * SOUMISSION FORMULAIRE MODIFICATION
 */
document
  .getElementById("form-request-change")
  ?.addEventListener("submit", function (e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerText;
    btn.innerText = "Envoi...";
    btn.disabled = true;

    const fd = new FormData(this);
    fd.append("action", "request_change_conge");

    fetch(dayoff_ajax_url, { method: "POST", body: fd })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          showToast(data.data, "success");
          fermerModaleModif();
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(data.data, "danger");
          btn.innerText = originalText;
          btn.disabled = false;
        }
      })
      .catch(() => {
        showToast("Erreur de connexion", "danger");
        btn.innerText = originalText;
        btn.disabled = false;
      });
  });

/**
 * JQUERY — SOUMISSIONS AJAX
 */
jQuery(document).ready(function ($) {
  $("#form-conge").on("submit", function (e) {
    e.preventDefault();
    let formData = new FormData(this);
    formData.append("action", "submit_conge");

    $.ajax({
      url: dayoff_ajax_url,
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      success: function (response) {
        if (response.success) {
          showToast("Demande envoyée !", "success");
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(response.data, "danger");
        }
      },
    });
  });

  $("#form-update-password").on("submit", function (e) {
    e.preventDefault();
    const newPass = $("#new_password").val();
    const confPass = $("#conf_password").val();

    if (newPass !== confPass) {
      showToast("Les mots de passe ne sont pas identiques.", "danger");
      return;
    }

    $.post(
      dayoff_ajax_url,
      {
        action: "update_user_password",
        new_password: newPass,
        conf_password: confPass,
      },
      function (response) {
        if (response.success) {
          showToast(response.data, "success");
          $("#new_password, #conf_password").val("");
        } else {
          showToast(response.data, "danger");
        }
      },
    );
  });

  $("#form-config-global").on("submit", function (e) {
    e.preventDefault();
    const btn = $(this).find('button[type="submit"]');
    const originalText = btn.text();
    btn.prop("disabled", true).text("Enregistrement...");

    let formData = new FormData(this);
    formData.append("action", "save_global_config");

    $.ajax({
      url: dayoff_ajax_url,
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      success: function (response) {
        if (response.success) {
          showToast(response.data, "success");
        } else {
          showToast("Erreur : " + response.data, "danger");
        }
        btn.prop("disabled", false).text(originalText);
      },
      error: function () {
        showToast("Le serveur ne répond pas", "danger");
        btn.prop("disabled", false).text(originalText);
      },
    });
  });
});
