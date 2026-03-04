// --- VARIABLES GLOBALES ---
// On les place en haut, mais on les utilise avec prudence
const boutonDemande = document.querySelector(".menu header .button");
const modalDemande = document.querySelector(".demande-container");
const btnFermer = document.querySelector(".close-icon");
const btnAnnuler = document.querySelector(".btn-cancel");
const overlay = document.getElementById("overlay");

let calendar;

// --- INITIALISATION AU CHARGEMENT ---
document.addEventListener("DOMContentLoaded", function () {
  console.log("DayOff JS chargé !");

  // 1. GESTION DE L'OUVERTURE/FERMETURE DE LA MODALE
  if (boutonDemande && modalDemande) {
    boutonDemande.addEventListener("click", () => {
      modalDemande.style.display = "block";
      if (overlay) overlay.style.display = "block";
    });
  }

  [btnFermer, btnAnnuler].forEach((el) => {
    if (el && modalDemande) {
      el.addEventListener("click", () => {
        modalDemande.style.display = "none";
        if (overlay) overlay.style.display = "none";
      });
    }
  });

  // 2. INITIALISATION DU CALENDRIER (CORRIGÉE)
  const calendarEl = document.getElementById("calendar");
  if (calendarEl && typeof FullCalendar !== "undefined") {
    calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: "dayGridMonth",
      locale: "fr",
      firstDay: 1,
      headerToolbar: {
        left: "prev,next today", // Correction de l'ordre pour le style
        center: "title",
        right: "dayGridMonth,listMonth",
      },
      buttonText: {
        today: "Aujourd'hui",
        month: "Mois",
        list: "Liste",
      },
      // Utilise la variable congesEvents injectée par le PHP
      events: typeof congesEvents !== "undefined" ? congesEvents : [],
      height: "auto",
      eventDisplay: "block",
    });
    calendar.render();
    console.log("Calendrier initialisé avec succès");
  }

  // 3. GESTION DU CHANGEMENT D'ONGLETS
  document.querySelectorAll(".menu-item").forEach((link) => {
    link.addEventListener("click", function (e) {
      const targetId = this.getAttribute("data-target");
      if (!targetId || targetId === "#") return;
      e.preventDefault();

      // Gérer les classes actives sur le menu
      document
        .querySelectorAll(".menu-item")
        .forEach((m) => m.classList.remove("active"));
      this.classList.add("active");

      // Cacher toutes les sections
      document.querySelectorAll(".tab-content").forEach((section) => {
        section.classList.remove("active");
        section.style.display = "none";
      });

      // Afficher la cible
      const targetSection = document.getElementById(targetId);
      if (targetSection) {
        targetSection.classList.add("active");
        targetSection.style.display = "block";

        // Fix pour le calendrier quand il devient visible
        if (targetId === "calendrier" && calendar) {
          setTimeout(() => {
            calendar.updateSize();
          }, 50); // Un léger délai pour laisser le temps à la section de passer en display: block
        }
      }
    });
  });

  // 4. ENVOI DU FORMULAIRE EN AJAX (LE MOTEUR)
  const formConge = document.getElementById("form-conge");
  if (formConge) {
    console.log("Formulaire de demande détecté !");

    formConge.addEventListener("submit", function (e) {
      e.preventDefault();
      console.log("Envoi de la demande en cours...");

      const formData = new FormData(this);
      formData.append("action", "submit_conge"); // Identifiant pour le PHP

      // On utilise le chemin relatif WP standard
      fetch(dayoff_ajax_url, {
        method: "POST",
        body: formData,
      })
        .then((response) => {
          if (!response.ok) throw new Error("Erreur serveur");
          return response.json();
        })
        .then((data) => {
          console.log("Réponse du serveur :", data);
          if (data.success) {
            alert("Votre demande a bien été envoyée !");
            location.reload(); // Recharge pour voir la ligne dans le tableau
          } else {
            alert("Erreur : " + data.data);
          }
        })
        .catch((error) => {
          console.error("Erreur Fetch:", error);
          alert(
            "Erreur de connexion au serveur. Vérifiez que vous êtes connecté.",
          );
        });
    });
  } else {
    console.error("ERREUR : Formulaire #form-conge introuvable dans le HTML.");
  }
});

// --- SYSTÈME DE SUPPRESSION ---
const tableBodyDelete = document.getElementById("tableBody");

if (tableBodyDelete) {
  tableBodyDelete.addEventListener("click", function (e) {
    // On cherche si on a cliqué sur le bouton poubelle ou l'icône à l'intérieur
    const deleteBtn = e.target.closest(".btn-icon.delete");

    if (deleteBtn) {
      const demandeId = deleteBtn.getAttribute("data-id");

      // 1. POPUP DE CONFIRMATION
      if (
        confirm(
          "Êtes-vous sûr de vouloir supprimer cette demande ? Cette action est irréversible.",
        )
      ) {
        // 2. ENVOI AJAX
        const formData = new FormData();
        formData.append("action", "delete_conge");
        formData.append("demande_id", demandeId);

        fetch(dayoff_ajax_url, {
          // On utilise l'URL dynamique définie avant
          method: "POST",
          body: formData,
        })
          .then((res) => res.json())
          .then((data) => {
            if (data.success) {
              // 3. EFFET VISUEL : Supprimer la ligne du tableau proprement
              const row = deleteBtn.closest("tr");
              row.style.transition = "all 0.3s ease";
              row.style.opacity = "0";
              setTimeout(() => {
                row.remove();
                // Si le tableau est vide après suppression, on peut afficher un message
                if (tableBodyDelete.querySelectorAll("tr").length === 0) {
                  tableBodyDelete.innerHTML =
                    '<tr><td colspan="6" class="text-center">Aucune demande enregistrée.</td></tr>';
                }
              }, 300);
            } else {
              alert("Erreur : " + data.data);
            }
          })
          .catch((err) => console.error("Erreur suppression:", err));
      }
    }
  });
}

// --- FONCTIONS ACTIONS (Validation Admin) ---
function traiterDemande(btn, decision) {
  const row = btn.closest("tr");
  const nom = row.cells[0].innerText;

  if (confirm(`Confirmer la décision "${decision}" pour ${nom} ?`)) {
    // Cette partie devra aussi être reliée en AJAX plus tard
    row.style.opacity = "0.5";
    row.cells[3].innerHTML = `<span class="badge active">${decision}</span>`;
  }
}

// --- SYSTÈME DE FILTRES "MES DEMANDES" ---
const filterNom = document.getElementById("filterNom");
const filterType = document.getElementById("filterType");
const filterDebut = document.getElementById("filterDateDebut");
const filterFin = document.getElementById("filterDateFin");
const tableBody = document.getElementById("tableBody");

if (tableBody && filterNom) {
  function appliquerTousLesFiltres() {
    const nomRecherche = filterNom.value.toLowerCase();
    const typeSelectionne = filterType.value;
    const dateDebutSelectionnee = filterDebut.value
      ? new Date(filterDebut.value)
      : null;
    const dateFinSelectionnee = filterFin.value
      ? new Date(filterFin.value)
      : null;

    const rows = tableBody.getElementsByTagName("tr");

    for (let row of rows) {
      // On vérifie que la ligne n'est pas le message "Aucune demande"
      if (row.cells.length < 3) continue;

      const nomCell = row.cells[0].innerText.toLowerCase();
      const typeCell = row.cells[1].innerText.trim();
      const periodeTexte = row.cells[2].innerText;

      // Filtre NOM
      const matchNom = nomCell.includes(nomRecherche);

      // Filtre TYPE
      const matchType =
        typeSelectionne === "" || typeCell.includes(typeSelectionne);

      // Filtre DATES
      let matchDate = true;
      const matches = periodeTexte.match(/(\d{2}\/\d{2}\/\d{4})/g);

      if (matches && matches.length === 2) {
        const rowDebut = convertirDateFr(matches[0]);
        const rowFin = convertirDateFr(matches[1]);

        if (dateDebutSelectionnee && rowDebut < dateDebutSelectionnee)
          matchDate = false;
        if (dateFinSelectionnee && rowFin > dateFinSelectionnee)
          matchDate = false;
      }

      // Affichage final de la ligne
      row.style.display = matchNom && matchType && matchDate ? "" : "none";
    }
  }

  // Fonction utilitaire pour transformer "25/02/2026" en objet Date JS
  function convertirDateFr(dateStr) {
    const [jour, mois, annee] = dateStr.split("/");
    return new Date(annee, mois - 1, jour);
  }

  // Écouteurs d'événements
  filterNom.addEventListener("input", appliquerTousLesFiltres);
  filterType.addEventListener("change", appliquerTousLesFiltres);
  if (filterDebut)
    filterDebut.addEventListener("change", appliquerTousLesFiltres);
  if (filterFin) filterFin.addEventListener("change", appliquerTousLesFiltres);
}
function resetFilters() {
  ["filterNom", "filterType", "filterDateDebut", "filterDateFin"].forEach(
    (id) => {
      const el = document.getElementById(id);
      if (el) el.value = "";
    },
  );

  const rows = document.querySelectorAll("#tableBody tr");
  rows.forEach((row) => (row.style.display = ""));
}

function validerDemande(id, decision) {
  if (
    !confirm(`Voulez-vous vraiment marquer cette demande comme ${decision} ?`)
  )
    return;

  // Récupérer le commentaire s'il y en a un
  const row = document.querySelector(`tr[data-id="${id}"]`);
  const commentaire = row ? row.querySelector(".comm-admin").value : "";

  const formData = new FormData();
  formData.append("action", "traiter_demande_admin");
  formData.append("demande_id", id);
  formData.append("decision", decision);
  formData.append("commentaire_admin", commentaire);

  fetch(dayoff_ajax_url, {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        // Effet visuel : On fait disparaître la ligne
        row.style.transition = "all 0.5s ease";
        row.style.transform = "translateX(20px)";
        row.style.opacity = "0";
        setTimeout(() => row.remove(), 500);
      } else {
        alert("Erreur : " + data.data);
      }
    });
}
