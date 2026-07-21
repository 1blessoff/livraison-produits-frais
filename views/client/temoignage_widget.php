<?php
// views/client/temoignage_widget.php
// Widget flottant pour les témoignages - Version optimisée
?>

<style>
    /* Widget Témoignage Flottant */
    .widget-temoignage {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 9999;
        width: 350px;
        max-width: calc(100vw - 40px);
        background: #ffffff;
        border: 1px solid #e5e5e5;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        padding: 20px 22px 18px;
        display: none;
        transition: all 0.3s ease;
    }
    
    .widget-temoignage.visible {
        display: block;
        animation: slideUpWidget 0.4s ease-out;
    }
    
    @keyframes slideUpWidget {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .widget-temoignage .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    
    .widget-temoignage .widget-title {
        font-weight: 700;
        font-size: 15px;
        margin: 0;
        letter-spacing: 0.5px;
    }
    
    .widget-temoignage .widget-title i {
        color: #ffc107;
        margin-right: 6px;
    }
    
    .widget-temoignage .widget-close {
        background: none;
        border: none;
        font-size: 18px;
        color: #999;
        cursor: pointer;
        padding: 0 4px;
        line-height: 1;
    }
    
    .widget-temoignage .widget-close:hover {
        color: #111;
    }
    
    .widget-temoignage .widget-subtitle {
        font-size: 12px;
        color: #888;
        margin-bottom: 14px;
        line-height: 1.4;
    }
    
    .widget-temoignage .form-control-widget {
        width: 100%;
        border: 1px solid #e5e5e5;
        padding: 8px 12px;
        font-size: 13px;
        font-family: inherit;
        resize: vertical;
        min-height: 70px;
        border-radius: 0;
    }
    
    .widget-temoignage .form-control-widget:focus {
        border-color: #111;
        outline: none;
    }
    
    .widget-temoignage .stars-widget {
        display: flex;
        gap: 6px;
        font-size: 24px;
        margin: 10px 0 12px 0;
        cursor: pointer;
    }
    
    .widget-temoignage .stars-widget i {
        color: #ddd;
        transition: color 0.2s;
    }
    
    .widget-temoignage .stars-widget i.active {
        color: #ffc107;
    }
    
    .widget-temoignage .stars-widget i:hover {
        color: #ffc107;
    }
    
    .widget-temoignage .checkbox-widget {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 10px 0 14px 0;
        font-size: 12px;
        color: #555;
        cursor: pointer;
    }
    
    .widget-temoignage .checkbox-widget input[type="checkbox"] {
        width: 16px;
        height: 16px;
        border: 1px solid #ccc;
        cursor: pointer;
        border-radius: 0;
        accent-color: #111;
    }
    
    .widget-temoignage .btn-widget-submit {
        background: #111111;
        color: #ffffff;
        border: 1px solid #111111;
        padding: 10px 18px;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.8px;
        width: 100%;
        cursor: pointer;
        transition: all 0.3s;
        border-radius: 0;
    }
    
    .widget-temoignage .btn-widget-submit:hover {
        background: #ffffff;
        color: #111111;
    }
    
    .widget-temoignage .btn-widget-submit:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .widget-temoignage .widget-success {
        text-align: center;
        padding: 10px 0;
        display: none;
    }
    
    .widget-temoignage .widget-success i {
        font-size: 32px;
        color: #28a745;
        display: block;
        margin-bottom: 6px;
    }
    
    .widget-temoignage .widget-success p {
        font-size: 13px;
        margin: 0;
        color: #555;
    }
    
    .widget-temoignage .widget-error {
        color: #dc3545;
        font-size: 12px;
        margin: 5px 0 10px 0;
        display: none;
    }
    
    /* Bouton déclencheur */
    .widget-trigger {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 9998;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #111111;
        color: #ffffff;
        border: none;
        font-size: 20px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        transition: all 0.3s;
        display: none;
        align-items: center;
        justify-content: center;
    }
    
    .widget-trigger:hover {
        transform: scale(1.06);
        background: #333;
    }
    
    .widget-trigger.show {
        display: flex;
    }
    
    @media (max-width: 576px) {
        .widget-temoignage {
            bottom: 15px;
            right: 15px;
            padding: 16px 18px 14px;
            width: calc(100vw - 30px);
        }
        .widget-trigger {
            bottom: 20px;
            right: 20px;
            width: 44px;
            height: 44px;
            font-size: 17px;
        }
        .widget-temoignage .stars-widget {
            font-size: 20px;
        }
    }
</style>

<?php
// ============================================
// 1. VÉRIFIER SI L'UTILISATEUR PEUT VOIR LE WIDGET
// ============================================
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$show_widget = false;
$has_testified = false;
$rappel_date = null;

if ($user_id > 0 && $_SESSION['user_role'] === 'client') {
    try {
        // 1. Vérifier si l'utilisateur a déjà donné son avis
        $stmt = $pdo->prepare("SELECT id, rappeler_le FROM temoignages WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $testimony = $stmt->fetch();
        
        if ($testimony) {
            $has_testified = true;
            $rappel_date = $testimony['rappeler_le'];
        }
        
        // 2. Si l'utilisateur n'a pas encore témoigné, vérifier s'il a des commandes
        if (!$has_testified) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM commandes WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $nb_commandes = $stmt->fetchColumn();
            
            // Afficher le widget seulement si l'utilisateur a au moins 1 commande
            if ($nb_commandes > 0) {
                $show_widget = true;
            }
        }
        
        // 3. Si l'utilisateur a coché "Me rappeler plus tard" et que 3 jours sont passés
        if ($has_testified && $rappel_date && strtotime($rappel_date) <= time()) {
            // Réafficher le widget pour un nouveau témoignage
            $show_widget = true;
            $has_testified = false; // Permet de re-soumettre
        }
        
    } catch (PDOException $e) {
        $show_widget = false;
    }
}

// Ne pas afficher le widget si les conditions ne sont pas remplies
if (!$show_widget) {
    return;
}
?>

<!-- BOUTON DÉCLENCHEUR -->
<button class="widget-trigger show" id="widgetTrigger" title="Donner mon avis">
    <i class="bi bi-star-fill"></i>
</button>

<!-- WIDGET TÉMOIGNAGE -->
<div class="widget-temoignage" id="widgetTemoignage">
    <div class="widget-header">
        <div class="widget-title">
            <i class="bi bi-star-fill"></i> Votre avis compte
        </div>
        <button class="widget-close" id="widgetClose" title="Fermer">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    
    <div class="widget-subtitle">Partagez votre expérience en quelques secondes.</div>
    
    <!-- Formulaire -->
    <div id="widgetForm">
        <textarea class="form-control-widget" id="widgetMessage" placeholder="Décrivez votre expérience..." rows="3"></textarea>
        
        <div class="widget-error" id="widgetError">Veuillez saisir un message.</div>
        
        <div style="margin-top: 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #888; letter-spacing: 0.5px;">Votre note</div>
        <div class="stars-widget" id="widgetStars">
            <i class="bi bi-star-fill" data-value="1"></i>
            <i class="bi bi-star-fill" data-value="2"></i>
            <i class="bi bi-star-fill" data-value="3"></i>
            <i class="bi bi-star-fill" data-value="4"></i>
            <i class="bi bi-star-fill" data-value="5"></i>
        </div>
        <input type="hidden" id="widgetNote" value="5">
        
        <label class="checkbox-widget">
            <input type="checkbox" id="widgetNotifier">
            <i class="bi bi-bell me-1"></i> Me rappeler dans 3 jours
        </label>
        
        <button class="btn-widget-submit" id="widgetSubmit">
            <i class="bi bi-send me-2"></i> Envoyer mon avis
        </button>
    </div>
    
    <!-- Message de succès -->
    <div class="widget-success" id="widgetSuccess">
        <i class="bi bi-check-circle-fill"></i>
        <p>Merci ! Votre avis a été envoyé.<br><small style="color:#999;">Il sera visible après validation.</small></p>
    </div>
</div>

<script>
// ============================================
// WIDGET TÉMOIGNAGE
// ============================================
(function() {
    'use strict';
    
    var trigger = document.getElementById('widgetTrigger');
    var popup = document.getElementById('widgetTemoignage');
    var closeBtn = document.getElementById('widgetClose');
    var submitBtn = document.getElementById('widgetSubmit');
    var messageInput = document.getElementById('widgetMessage');
    var noteInput = document.getElementById('widgetNote');
    var errorDiv = document.getElementById('widgetError');
    var notifierCheck = document.getElementById('widgetNotifier');
    var formDiv = document.getElementById('widgetForm');
    var successDiv = document.getElementById('widgetSuccess');
    var stars = document.querySelectorAll('#widgetStars i');
    
    var selectedNote = 5;
    var popupOpen = false;
    var userId = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
    var userNom = '<?= addslashes($_SESSION['user_nom'] ?? 'Anonyme') ?>';
    var userPrenom = '<?= addslashes($_SESSION['user_prenom'] ?? 'Client') ?>';
    
    // --- Gestion des étoiles ---
    function updateStars(value) {
        for (var i = 0; i < stars.length; i++) {
            if (i < value) {
                stars[i].classList.add('active');
            } else {
                stars[i].classList.remove('active');
            }
        }
        noteInput.value = value;
        selectedNote = value;
    }
    
    updateStars(5);
    
    for (var s = 0; s < stars.length; s++) {
        (function(index) {
            stars[index].addEventListener('mouseover', function() {
                updateStars(parseInt(this.dataset.value));
            });
            stars[index].addEventListener('click', function() {
                selectedNote = parseInt(this.dataset.value);
                updateStars(selectedNote);
            });
        })(s);
    }
    
    document.getElementById('widgetStars').addEventListener('mouseleave', function() {
        updateStars(selectedNote);
    });
    
    // --- Ouvrir / Fermer ---
    trigger.addEventListener('click', function() {
        popup.classList.add('visible');
        trigger.classList.remove('show');
        popupOpen = true;
        errorDiv.style.display = 'none';
    });
    
    function closePopup() {
        popup.classList.remove('visible');
        setTimeout(function() {
            if (!popup.classList.contains('visible')) {
                trigger.classList.add('show');
            }
        }, 300);
        popupOpen = false;
    }
    
    closeBtn.addEventListener('click', closePopup);
    
    document.addEventListener('click', function(e) {
        if (popupOpen && !popup.contains(e.target) && e.target !== trigger) {
            closePopup();
        }
    });
    
    // --- Soumettre ---
    submitBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        var message = messageInput.value.trim();
        
        if (message.length < 5) {
            errorDiv.style.display = 'block';
            messageInput.focus();
            return;
        }
        errorDiv.style.display = 'none';
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Envoi...';
        
        var notifier = notifierCheck.checked ? '1' : '0';
        var rappeler_le = notifier === '1' ? '<?= date('Y-m-d H:i:s', strtotime('+3 days')) ?>' : null;
        
        var formData = new FormData();
        formData.append('action', 'add');
        formData.append('user_id', userId);
        formData.append('nom', userNom || 'Anonyme');
        formData.append('prenom', userPrenom || 'Client');
        formData.append('message', message);
        formData.append('note', selectedNote);
        formData.append('notifier', notifier);
        formData.append('rappeler_le', rappeler_le);
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/panier-menagere/ajax/temoignage_handler.php', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            formDiv.style.display = 'none';
                            successDiv.style.display = 'block';
                            
                            setTimeout(function() {
                                closePopup();
                                setTimeout(function() {
                                    formDiv.style.display = 'block';
                                    successDiv.style.display = 'none';
                                    messageInput.value = '';
                                    updateStars(5);
                                    selectedNote = 5;
                                    notifierCheck.checked = false;
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = '<i class="bi bi-send me-2"></i> Envoyer mon avis';
                                    document.getElementById('widgetTemoignage').style.display = 'none';
                                    document.getElementById('widgetTrigger').style.display = 'none';
                                }, 400);
                            }, 2500);
                        } else {
                            alert(response.message || 'Erreur lors de l\'envoi.');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="bi bi-send me-2"></i> Envoyer mon avis';
                        }
                    } catch (e) {
                        alert('Erreur de réponse du serveur.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-send me-2"></i> Envoyer mon avis';
                    }
                } else {
                    alert('Erreur de connexion au serveur.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send me-2"></i> Envoyer mon avis';
                }
            }
        };
        xhr.send(formData);
    });
    
    // --- Affichage automatique ---
    var popupClosed = localStorage.getItem('widget_temoignage_closed');
    if (!popupClosed) {
        setTimeout(function() {
            popup.classList.add('visible');
            trigger.classList.remove('show');
            popupOpen = true;
        }, 6000);
    } else {
        trigger.classList.add('show');
    }
    
    // Mémoriser la fermeture manuelle
    closeBtn.addEventListener('click', function() {
        localStorage.setItem('widget_temoignage_closed', 'true');
    });
    
})();
</script>