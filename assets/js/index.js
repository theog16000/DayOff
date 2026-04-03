/**
 * --- VARIABLES GLOBALES ---
 */
let calendar;
const boutonDemande = document.querySelector(".menu header .button");
const modalDemande = document.querySelector(".demande-container");
const overlay = document.getElementById("overlay");

/**
 * --- SYSTÈME DE PAGINATION ---
 */
const PAGINATION_CONFIG = {
  mes_demandes: { selector: "#tableBody tr", perPage: 5 },
  validation_conges: {
    selector: "#validation_conges .user-row-item",
    perPage: 5,
  },
  modifications: { selector: "#container-modifs-list .modif-row", perPage: 5 },
  gestion_users: {
    selector: ".user-list-container .user-row-item[data-name]",
    perPage: 5,
  },
};

const paginationState = {};

function initPagination(sectionKey) {
  const config = PAGINATION_CONFIG[sectionKey];
  if (!config) return;
  paginationState[sectionKey] = { currentPage: 1 };
  renderPagination(sectionKey);
}

function getAllItems(sectionKey) {
  const config = PAGINATION_CONFIG[sectionKey];
  let items = Array.from(document.querySelectorAll(config.selector));
  if (sectionKey === "mes_demandes") {
    items = items.filter((row) => row.cells && row.cells.length >= 3);
  }
  // Initialiser data-filtered-out sur les nouveaux items
  items.forEach((item) => {
    if (!item.dataset.filteredOut) item.dataset.filteredOut = "false";
  });
  return items;
}

function renderPagination(sectionKey) {
  const config = PAGINATION_CONFIG[sectionKey];
  const state = paginationState[sectionKey];
  if (!state) return;

  const allItems = getAllItems(sectionKey);
  const visibleItems = allItems.filter(
    (item) => item.dataset.filteredOut !== "true",
  );

  const totalItems = visibleItems.length;
  const totalPages = Math.ceil(totalItems / config.perPage);
  const currentPage = Math.min(state.currentPage, totalPages || 1);
  state.currentPage = currentPage;

  const start = (currentPage - 1) * config.perPage;
  const end = start + config.perPage;

  // Masquer tous les items
  allItems.forEach((item) =>
    item.style.setProperty("display", "none", "important"),
  );

  // Afficher ceux de la page courante parmi les visibles
  visibleItems.forEach((item, index) => {
    if (index >= start && index < end) {
      item.style.removeProperty("display");
      if (
        item.classList.contains("user-row-item") ||
        item.classList.contains("modif-row")
      ) {
        item.style.display = "grid";
      }
    }
  });

  renderPaginationControls(
    sectionKey,
    currentPage,
    totalPages,
    totalItems,
    config.perPage,
  );
}

function renderPaginationControls(
  sectionKey,
  currentPage,
  totalPages,
  totalItems,
  perPage,
) {
  const containerId = `pagination-${sectionKey}`;
  let container = document.getElementById(containerId);

  if (!container) {
    container = document.createElement("div");
    container.id = containerId;
    const targets = {
      mes_demandes: () =>
        document.querySelector("#mes_demandes .table-responsive"),
      validation_conges: () =>
        document.querySelector("#validation_conges .user-list-container"),
      modifications: () => document.getElementById("container-modifs-list"),
      gestion_users: () =>
        document.querySelector("#gestion_users .user-list-container"),
    };
    const target = targets[sectionKey]?.();
    if (target) target.after(container);
  }

  const total = getAllItems(sectionKey).filter(
    (i) => i.dataset.filteredOut !== "true",
  ).length;

  if (totalPages <= 1) {
    container.innerHTML = `
            <div style="padding:12px 0;border-top:1px solid #f3f4f6;text-align:center;">
                <span style="font-size:12px;color:#9ca3af;">
                    <strong style="color:#374151;">${total}</strong> élément${total > 1 ? "s" : ""}
                </span>
            </div>`;
    return;
  }

  const start = (currentPage - 1) * perPage + 1;
  const end = Math.min(currentPage * perPage, totalItems);
  const delta = 1;
  let pagesHtml = "";

  for (let i = 1; i <= totalPages; i++) {
    if (
      i === 1 ||
      i === totalPages ||
      (i >= currentPage - delta && i <= currentPage + delta)
    ) {
      const isActive = i === currentPage;
      pagesHtml += `
                <button onclick="goToPage('${sectionKey}', ${i})" style="
                    min-width:36px;height:36px;border-radius:8px;
                    border:${isActive ? "2px solid #1f2937" : "1px solid #e5e7eb"};
                    background:${isActive ? "#1f2937" : "#fff"};
                    color:${isActive ? "#fff" : "#374151"};
                    font-size:13px;font-weight:${isActive ? "700" : "400"};
                    cursor:pointer;padding:0 10px;
                    box-shadow:${isActive ? "0 2px 6px rgba(0,0,0,0.15)" : "none"};
                ">${i}</button>`;
    } else if (i === currentPage - delta - 1 || i === currentPage + delta + 1) {
      pagesHtml += `<span style="color:#9ca3af;font-size:14px;padding:0 2px;line-height:36px;">•••</span>`;
    }
  }

  container.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 0;margin-top:4px;border-top:1px solid #f3f4f6;">
            <span style="font-size:12px;color:#9ca3af;white-space:nowrap;">
                <strong style="color:#374151;">${start}–${end}</strong> sur <strong style="color:#374151;">${totalItems}</strong>
            </span>
            <div style="display:flex;align-items:center;gap:6px;">
                <button onclick="goToPage('${sectionKey}', ${currentPage - 1})"
                    ${currentPage === 1 ? "disabled" : ""}
                    style="display:flex;align-items:center;gap:6px;height:36px;padding:0 14px;border-radius:8px;
                        border:1px solid ${currentPage === 1 ? "#f3f4f6" : "#e5e7eb"};
                        background:${currentPage === 1 ? "#f9fafb" : "#fff"};
                        color:${currentPage === 1 ? "#d1d5db" : "#374151"};
                        font-size:13px;cursor:${currentPage === 1 ? "not-allowed" : "pointer"};font-weight:500;">
                    ← Précédent
                </button>
                <div style="display:flex;align-items:center;gap:4px;">${pagesHtml}</div>
                <button onclick="goToPage('${sectionKey}', ${currentPage + 1})"
                    ${currentPage === totalPages ? "disabled" : ""}
                    style="display:flex;align-items:center;gap:6px;height:36px;padding:0 14px;border-radius:8px;
                        border:1px solid ${currentPage === totalPages ? "#f3f4f6" : "#e5e7eb"};
                        background:${currentPage === totalPages ? "#f9fafb" : "#1f2937"};
                        color:${currentPage === totalPages ? "#d1d5db" : "#fff"};
                        font-size:13px;cursor:${currentPage === totalPages ? "not-allowed" : "pointer"};font-weight:500;">
                    Suivant →
                </button>
            </div>
        </div>`;
}

function goToPage(sectionKey, page) {
  const config = PAGINATION_CONFIG[sectionKey];
  const state = paginationState[sectionKey];
  if (!state) return;

  const totalPages = Math.ceil(getAllItems(sectionKey).length / config.perPage);
  if (page < 1 || page > totalPages) return;

  state.currentPage = page;
  renderPagination(sectionKey);
}

function resetPaginationAndFilter(sectionKey, filterFn) {
  if (paginationState[sectionKey]) paginationState[sectionKey].currentPage = 1;
  filterFn();
  renderPagination(sectionKey);
}

/**
 * --- TOASTS ---
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
 * --- FILTRES ---
 */
function filtrerTableau() {
  const tableBody = document.getElementById("tableBody");
  if (!tableBody) return;

  const fType =
    document.getElementById("filterType")?.value.toLowerCase() || "";
  const fStatut =
    document.getElementById("filterStatut")?.value.toLowerCase() || "";
  const fDebut = document.getElementById("filterDateDebut")?.value || "";
  const fFin = document.getElementById("filterDateFin")?.value || "";

  tableBody.querySelectorAll("tr").forEach((row) => {
    if (row.cells.length < 3) return;
    const rType = (row.dataset.type || "").toLowerCase();
    const rStatut = (row.dataset.statut || "").toLowerCase();
    const rDebut = row.dataset.debut || "";
    const rFin = row.dataset.fin || "";

    const matchType = fType === "" || rType.includes(fType);
    const matchStatut = fStatut === "" || rStatut.includes(fStatut);
    let matchDate = true;
    if (fDebut && rFin < fDebut) matchDate = false;
    if (fFin && rDebut > fFin) matchDate = false;

    if (matchType && matchStatut && matchDate) {
      row.dataset.filteredOut = "false";
    } else {
      row.dataset.filteredOut = "true";
      row.style.setProperty("display", "none", "important");
    }
  });
}

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

    if (matchName && matchType && matchDate) {
      row.dataset.filteredOut = "false";
    } else {
      row.dataset.filteredOut = "true";
      row.style.setProperty("display", "none", "important");
    }
  });
}

function filterModifications() {
  const nameSearch =
    document.getElementById("filterModifName")?.value.toLowerCase() || "";
  const actionSearch =
    document.getElementById("filterModifAction")?.value || "";

  document.querySelectorAll(".modif-row").forEach((row) => {
    const matchName = (row.getAttribute("data-user") || "").includes(
      nameSearch,
    );
    const matchAction =
      actionSearch === "" || row.getAttribute("data-action") === actionSearch;

    if (matchName && matchAction) {
      row.dataset.filteredOut = "false";
    } else {
      row.dataset.filteredOut = "true";
      row.style.setProperty("display", "none", "important");
    }
  });

  if (paginationState["modifications"]) {
    paginationState["modifications"].currentPage = 1;
    renderPagination("modifications");
  }
}

/**
 * --- MODALE TRAÇABILITÉ ---
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
        <span style="font-size:11px;padding:3px 12px;border-radius:20px;font-weight:600;border:1px solid currentColor;${isSuppr ? "background:#fee2e2;color:#b91c1c;" : "background:#fef3c7;color:#92400e;"}">
            ${isSuppr ? "Annulation demandée" : "Modification demandée"}
        </span>
        <span style="font-size:11px;color:#9ca3af;margin-left:8px;">En attente de validation</span>`;

  document.getElementById("trace-avant").innerText =
    `${data.type_conge} · ${data.date_debut} → ${data.date_fin}`;

  if (isSuppr) {
    document.getElementById("trace-apres-label").innerText = "Après annulation";
    document.getElementById("trace-apres").innerText = "Supprimé";
    document.getElementById("trace-apres").style.color = "#dc2626";
  } else {
    document.getElementById("trace-apres-label").innerText =
      "Nouvelles dates souhaitées";
    document.getElementById("trace-apres").innerText =
      `${data.nouveau_type || data.type_conge} · ${data.nouvelle_date_debut || "—"} → ${data.nouvelle_date_fin || "—"}`;
    document.getElementById("trace-apres").style.color = "#059669";
  }

  document.getElementById("trace-raison").innerText = `"${data.raison}"`;
  document.getElementById("trace-date").innerText = data.created_at;

  modal.style.setProperty("display", "flex", "important");
  if (window.lucide) lucide.createIcons();
}

function fermerTraceModif() {
  const modal = document.getElementById("modal-trace-modif");
  if (modal) modal.style.setProperty("display", "none", "important");
}
/**
 * --- FILTRE HISTORIQUE MODIFICATIONS (panel-historique) ---
 */
let currentFiltreAction = "tous";

function filtrerHistoriqueModifs(reset) {
  if (reset === "reset") {
    const selAction = document.getElementById("filtre-hm-action");
    const selStatut = document.getElementById("filtre-hm-statut");
    if (selAction) selAction.value = "";
    if (selStatut) selStatut.value = "";
  }

  const action = document.getElementById("filtre-hm-action")?.value || "";
  const statut = document.getElementById("filtre-hm-statut")?.value || "";

  let visible = 0;
  document.querySelectorAll(".hm-card").forEach((card) => {
    const matchAction =
      action === "" || card.getAttribute("data-action") === action;
    const matchStatut =
      statut === "" || card.getAttribute("data-statut") === statut;

    if (matchAction && matchStatut) {
      card.style.display = "flex";
      visible++;
    } else {
      card.style.display = "none";
    }
  });

  const count = document.getElementById("filtre-hm-count");
  if (count) count.innerText = `${visible} entrée${visible > 1 ? "s" : ""}`;
}
/**
 * --- INITIALISATION DOM ---
 */
document.addEventListener("DOMContentLoaded", function () {
  console.log("DayOff Engine chargé !");

  const menuItems = document.querySelectorAll(".menu-item");
  const tabContents = document.querySelectorAll(".tab-content");
  const modaleDemande = document.getElementById("modale-demande");
  const overlayDemande = document.getElementById("overlay");
  const btnOuvrir =
    document.getElementById("btn-ouvrir-demande") ||
    document.querySelector(".menu header .button");
  const paginatedSections = [
    "mes_demandes",
    "validation_conges",
    "modifications",
    "gestion_users",
  ];

  // --- ONGLETS ---
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

    if (paginatedSections.includes(id)) {
      setTimeout(() => initPagination(id), 100);
    }
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
  setTimeout(() => switchMesDemandesTab("demandes"), 150);
  if (tabParam)
    window.history.replaceState({}, document.title, window.location.pathname);

  // --- MODALE DEMANDE ---
  btnOuvrir?.addEventListener("click", () => {
    if (modaleDemande) modaleDemande.style.display = "block";
    if (overlayDemande) overlayDemande.style.display = "block";
  });

  document.addEventListener("click", function (e) {
    if (e.target.closest("#modal-trace-modif")) return;
    if (e.target.closest(".btn-voir-modif")) return;

    const isCroix = e.target.closest(".close-icon");
    const isAnnuler = e.target.closest(".btn-cancel");
    const isOverlay = e.target === overlayDemande;

    if (isCroix || isAnnuler || isOverlay) {
      if (modaleDemande) modaleDemande.style.display = "none";
      if (overlayDemande) overlayDemande.style.display = "none";
    }
  });

  // --- FILTRES MES DEMANDES ---
  ["filterType", "filterStatut", "filterDateDebut", "filterDateFin"].forEach(
    (id) => {
      document.getElementById(id)?.addEventListener("input", () => {
        resetPaginationAndFilter("mes_demandes", filtrerTableau);
      });
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
      resetPaginationAndFilter("mes_demandes", filtrerTableau);
      showToast("Filtres réinitialisés", "info");
    });

  // --- FILTRES VALIDATIONS ADMIN ---
  ["adminFilterName", "adminFilterType", "adminFilterDate"].forEach((id) => {
    document.getElementById(id)?.addEventListener("input", () => {
      resetPaginationAndFilter("validation_conges", filtrerValidationsAdmin);
    });
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
      resetPaginationAndFilter("validation_conges", filtrerValidationsAdmin);
    });

  // --- FILTRES MODIFICATIONS ---
  document.getElementById("filterModifName")?.addEventListener("input", () => {
    resetPaginationAndFilter("modifications", filterModifications);
  });
  document
    .getElementById("filterModifAction")
    ?.addEventListener("change", () => {
      resetPaginationAndFilter("modifications", filterModifications);
    });
  document
    .getElementById("btn-reset-modif-filters")
    ?.addEventListener("click", () => {
      document.getElementById("filterModifName").value = "";
      document.getElementById("filterModifAction").value = "";
      resetPaginationAndFilter("modifications", filterModifications);
    });

  // --- REFRESH ---
  document
    .getElementById("btn-refresh-modifications")
    ?.addEventListener("click", function () {
      this.querySelector("i")?.classList.add("rotating");
      showToast("Actualisation...", "info");
      setTimeout(() => location.reload(), 800);
    });

  document
    .getElementById("btn-refresh-validations")
    ?.addEventListener("click", function () {
      this.querySelector("i")?.classList.add("rotating");
      showToast("Actualisation...", "info");
      setTimeout(() => location.reload(), 800);
    });

  // --- RECHERCHE UTILISATEURS ---
  document
    .getElementById("user-search")
    ?.addEventListener("input", function () {
      const val = this.value.toLowerCase();
      document.querySelectorAll(".user-row-item[data-name]").forEach((row) => {
        if (row.dataset.name.includes(val)) {
          row.dataset.filteredOut = "false";
        } else {
          row.dataset.filteredOut = "true";
          row.style.setProperty("display", "none", "important");
        }
      });
      if (paginationState["gestion_users"]) {
        paginationState["gestion_users"].currentPage = 1;
        renderPagination("gestion_users");
      }
    });

  // --- SOUMISSION CONGÉ ---
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

  // --- CONFIGURATION GLOBALE ---
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

  // --- GESTION ADMIN UTILISATEURS ---
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

      if (
        await customConfirm(
          "Réinitialiser ?",
          `Voulez-vous supprimer TOUT l'historique de ${userName} ? Cette action est irréversible.`,
          "danger",
        )
      ) {
        const fd = new FormData();
        fd.append("action", "reset_user_history");
        fd.append("target_user_id", userId);

        fetch(dayoff_ajax_url, { method: "POST", body: fd })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              showToast(data.data, "success");
              const lc = document.getElementById("user-leave-list");
              if (lc)
                lc.innerHTML =
                  "<li class='text-muted small'>Historique vidé.</li>";
              if (calendar)
                calendar.getEvents().forEach((ev) => {
                  if (ev.title.startsWith(userName)) ev.remove();
                });
            } else {
              showToast(data.data, "danger");
            }
          })
          .catch(() =>
            showToast("Erreur lors de la réinitialisation", "danger"),
          );
      }
    });

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
      const lc = document.getElementById("user-leave-list");
      if (lc)
        lc.innerHTML = "<li class='text-muted small'>Nouvel utilisateur</li>";
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

  // --- FERMER PANNEAU LATÉRAL ---
  document.addEventListener("click", function (e) {
    if (e.target.closest("#btn-close-edit-panel")) {
      const ep = document.getElementById("edit-panel");
      if (ep) {
        ep.querySelector(".sidebar-placeholder").style.display = "flex";
        ep.querySelector(".sidebar-content").style.display = "none";
        document.getElementById("form-admin-user-global")?.reset();
      }
    }
  });

  // --- BOUTON MODIF EN ATTENTE ---
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".btn-voir-modif");
    if (!btn) return;
    e.stopPropagation();
    ouvrirTraceModif(btn);
  });

  // --- BOUTON REFAIRE UNE DEMANDE ---
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".btn-refaire-demande");
    if (!btn) return;
    e.stopPropagation();

    const type = btn.getAttribute("data-type");
    const debut = btn.getAttribute("data-debut");
    const fin = btn.getAttribute("data-fin");

    const selectType = document.getElementById("type_conge");
    const inputDebut = document.getElementById("date_debut");
    const inputFin = document.getElementById("date_fin");
    const selectMoment = document.getElementById("moment_journee");
    const ctnDateFin = document.getElementById("container-date-fin");

    if (selectType)
      Array.from(selectType.options).forEach((opt) => {
        opt.selected = opt.value === type;
      });
    if (inputDebut) inputDebut.value = debut;
    if (inputFin) inputFin.value = fin;
    if (selectMoment) selectMoment.value = "full";
    if (ctnDateFin) ctnDateFin.style.display = "block";

    if (modaleDemande) modaleDemande.style.display = "block";
    if (overlayDemande) overlayDemande.style.display = "block";
    if (window.lucide) lucide.createIcons();
  });

  // --- DURÉE DYNAMIQUE ---
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

  // --- CALENDRIER ---
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
          let start = selectedStart,
            end = info.dateStr;
          if (end < start) [start, end] = [end, start];
          if (inD) inD.value = start;
          if (inF) inF.value = end;
          selectedStart = null;
          document
            .querySelectorAll(".fc-day")
            .forEach((d) => d.classList.remove("fc-day-selected-start"));
          if (modaleDemande) modaleDemande.style.display = "block";
          if (overlay) overlay.style.display = "block";
        }
      },
    });
    calendar.render();
  }

  // --- DEMI-JOURNÉES ---
  const selectMoment = document.getElementById("moment_journee");
  const ctnDateFin = document.getElementById("container-date-fin");
  const inputDateDebut = document.getElementById("date_debut");
  const inputDateFin = document.getElementById("date_fin");
  const labelDebut = document.getElementById("label-date-debut");

  if (selectMoment) {
    selectMoment.addEventListener("change", function () {
      if (this.value !== "full") {
        if (ctnDateFin) ctnDateFin.style.display = "none";
        if (inputDateFin) inputDateFin.removeAttribute("required");
        if (labelDebut) labelDebut.innerText = "Date du congé";
        if (inputDateFin && inputDateDebut)
          inputDateFin.value = inputDateDebut.value;
      } else {
        if (ctnDateFin) ctnDateFin.style.display = "block";
        if (inputDateFin) inputDateFin.setAttribute("required", "required");
        if (labelDebut) labelDebut.innerText = "Date de début";
      }
    });
    inputDateDebut?.addEventListener("change", function () {
      if (selectMoment.value !== "full" && inputDateFin)
        inputDateFin.value = this.value;
    });
  }

  if (window.lucide) lucide.createIcons();
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
        } else showToast(data.data || "Erreur", "danger");
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
        } else showToast(data.data || "Erreur", "danger");
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
        } else showToast(data.data || "Erreur", "danger");
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

/**
 * --- EXPORT CSV / PDF ---
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

document.addEventListener("change", function (e) {
  if (!e.target.classList.contains("user-export-checkbox")) return;
  const allCbs = document.querySelectorAll(".user-export-checkbox");
  const selectAll = document.getElementById("export-select-all");
  if (selectAll) selectAll.checked = Array.from(allCbs).every((c) => c.checked);
  majZoneSelectionnes();
});

document
  .getElementById("export-select-all")
  ?.addEventListener("change", function () {
    document
      .querySelectorAll(".user-export-checkbox")
      .forEach((cb) => (cb.checked = this.checked));
    majZoneSelectionnes();
  });

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

  const searchInput = document.getElementById("search-export-user");
  if (searchInput) searchInput.value = "";
  const selectAll = document.getElementById("export-select-all");
  if (selectAll) selectAll.checked = true;

  setTimeout(() => majZoneSelectionnes(), 50);
  modal.style.display = "flex";
  if (window.lucide) lucide.createIcons();
});

document
  .getElementById("search-export-user")
  ?.addEventListener("input", function () {
    const val = this.value.toLowerCase();
    document.querySelectorAll("#export-users-list label").forEach((item) => {
      const name = item.querySelector("span")?.innerText.toLowerCase() || "";
      item.style.display = name.includes(val) ? "flex" : "none";
    });
  });

document
  .getElementById("btn-confirm-export-csv")
  ?.addEventListener("click", () => {
    const ids = Array.from(
      document.querySelectorAll(".user-export-checkbox:checked"),
    ).map((cb) => cb.value);
    if (ids.length === 0) {
      showToast("Sélectionnez au moins un collaborateur", "danger");
      return;
    }

    const form = document.createElement("form");
    form.method = "POST";
    form.action = dayoff_ajax_url;
    [
      ["action", "export_users_csv"],
      ["group_ids", ids.join(",")],
    ].forEach(([name, value]) => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = value;
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    document.getElementById("modal-export-custom").style.display = "none";
    showToast("Génération du CSV en cours...", "success");
  });

document
  .getElementById("btn-confirm-export-pdf")
  ?.addEventListener("click", () => {
    const ids = Array.from(
      document.querySelectorAll(".user-export-checkbox:checked"),
    ).map((cb) => cb.value);
    if (ids.length === 0) {
      showToast("Sélectionnez au moins un collaborateur", "danger");
      return;
    }

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
 * --- FORMULAIRE MODIFICATION ---
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
 * --- JQUERY ---
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
        } else showToast(response.data, "danger");
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
        } else showToast(response.data, "danger");
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
        if (response.success) showToast(response.data, "success");
        else showToast("Erreur : " + response.data, "danger");
        btn.prop("disabled", false).text(originalText);
      },
      error: function () {
        showToast("Le serveur ne répond pas", "danger");
        btn.prop("disabled", false).text(originalText);
      },
    });
  });
});
/**
 * --- TABS MES DEMANDES ---
 */
function switchMesDemandesTab(tab) {
  const panelDemandes = document.getElementById("panel-demandes");
  const panelHistorique = document.getElementById("panel-historique");
  const btnDemandes = document.getElementById("tab-btn-demandes");
  const btnHistorique = document.getElementById("tab-btn-historique");

  if (!panelDemandes || !panelHistorique || !btnDemandes || !btnHistorique)
    return;

  // Style de base commun aux deux boutons
  const baseStyle =
    "padding:10px 20px;border:none;background:none;cursor:pointer;font-size:14px;font-weight:600;transition:all .2s;margin-bottom:-2px;";

  if (tab === "demandes") {
    panelDemandes.style.display = "block";
    panelHistorique.style.display = "none";
    btnDemandes.style.cssText =
      baseStyle + "color:#1f2937;border-bottom:2px solid #1f2937;";
    btnHistorique.style.cssText =
      baseStyle + "color:#6b7280;border-bottom:2px solid transparent;";

    if (paginationState["mes_demandes"]) {
      paginationState["mes_demandes"].currentPage = 1;
      renderPagination("mes_demandes");
    }
  } else {
    panelDemandes.style.display = "none";
    panelHistorique.style.display = "block";
    btnDemandes.style.cssText =
      baseStyle + "color:#6b7280;border-bottom:2px solid transparent;";
    btnHistorique.style.cssText =
      baseStyle + "color:#1f2937;border-bottom:2px solid #1f2937;";
  }

  if (window.lucide) lucide.createIcons();
}
