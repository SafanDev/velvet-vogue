<?php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
$page_css = 'wardrobe.css';
$page_js = 'wardrobe.js';
require_once '../Config/db.php';
include '../ReuseableUI/header.php';

// Strict Gender Validation on Load
$gender = $_GET['gender'] ?? 'Women';
if ($gender !== 'Men' && $gender !== 'Women') {
    $gender = 'Women';
}
?>

<main class="atelier-viewport">
    <div class="cinematic-grain"></div>
    <div class="atelier-ambient-light"></div>

    <div class="atelier-stage">
        <div class="stage-wrapper" id="stageWrapper">
            <img decoding="async" src="../Assets/images/wardrobe/base-<?= strtolower($gender) ?>.webp" class="layer base-model" id="baseModel" alt="Base Model">
            <img decoding="async" src="" class="layer dress-layer" id="layer-Footwear" style="z-index: 2;">
            <img decoding="async" src="" class="layer dress-layer" id="layer-Bottoms" style="z-index: 3;">
            <img decoding="async" src="" class="layer dress-layer" id="layer-Dresses" style="z-index: 4;">
            <img decoding="async" src="" class="layer dress-layer" id="layer-Tops" style="z-index: 5;">
            <img decoding="async" src="" class="layer dress-layer" id="layer-Tailoring" style="z-index: 6;">
            <img decoding="async" src="" class="layer dress-layer" id="layer-Outerwear" style="z-index: 7;">
            <img decoding="async" src="" class="layer dress-layer" id="layer-Bags" style="z-index: 8;">
            <img decoding="async" src="" class="layer dress-layer" id="layer-Accessories" style="z-index: 9;">
        </div>
    </div>

    <button class="focus-mode-btn" id="toggleUI" title="Toggle Focus Mode">
        <i class="fa-solid fa-eye-slash"></i>
    </button>

    <aside class="floating-ui panel-left" id="panelLeft">

        <div class="panel-section mb-4">
            <span class="micro-label text-gold">MODEL BASE</span>
            <div class="gender-switch-box">
                <button class="gen-btn <?= $gender == 'Men' ? 'active' : '' ?>" id="btn-Men" onclick="switchGender('Men')">MALE</button>
                <button class="gen-btn <?= $gender == 'Women' ? 'active' : '' ?>" id="btn-Women" onclick="switchGender('Women')">FEMALE</button>
            </div>
        </div>

        <div class="panel-header mb-3">
            <span class="micro-label text-gold">STEP 01</span>
            <h2 class="panel-title" style="font-size: 1.5rem;">Equipment</h2>
        </div>

        <div class="slot-grid">
            <button class="slot-btn" data-category="Accessories"><i class="fa-solid fa-glasses slot-icon"></i><span class="slot-name">Accessories</span></button>
            <button class="slot-btn active" data-category="Tops"><i class="fa-solid fa-shirt slot-icon"></i><span class="slot-name">Tops</span></button>
            <button class="slot-btn" data-category="Outerwear"><i class="fa-solid fa-vest slot-icon"></i><span class="slot-name">Outerwear</span></button>
            <button class="slot-btn" data-category="Tailoring & Suiting"><i class="fa-solid fa-user-tie slot-icon"></i><span class="slot-name">Tailoring</span></button>
            <button class="slot-btn" data-category="Dresses & Gowns"><i class="fa-solid fa-person-dress slot-icon"></i><span class="slot-name">Full Body</span></button>
            <button class="slot-btn" data-category="Bottoms"><i class="fa-solid fa-layer-group slot-icon"></i><span class="slot-name">Bottoms</span></button>
            <button class="slot-btn" data-category="Footwear"><i class="fa-solid fa-shoe-prints slot-icon"></i><span class="slot-name">Footwear</span></button>
            <button class="slot-btn" data-category="Bags"><i class="fa-solid fa-bag-shopping slot-icon"></i><span class="slot-name">Bags</span></button>
        </div>
    </aside>

    <aside class="floating-ui panel-right" id="panelRight">
        <div class="panel-header" style="padding: 25px 30px 15px 30px;">
            <span class="micro-label text-gold">STEP 02</span>
            <h2 class="panel-title" id="currentCatTitle">Tops</h2>
        </div>

        <div class="inventory-container" id="inventoryGrid">
            <div class="loader-vogue"></div>
        </div>

        <div class="cart-dock-card">
            <div class="dock-row">
                <span class="dock-label">OUTFIT VALUATION</span>
                <span class="dock-price">Rs. <span id="totalOutfitPrice">0</span></span>
            </div>

            <button class="btn-vogue-square mt-4" onclick="showSummaryModal()">
                <span class="btn-text">ADD LOOK TO CART</span>
                <span class="btn-border"></span>
            </button>

            <button class="btn-reset-card mt-3" onclick="resetWardrobe()">
                <i class="fa-solid fa-arrows-rotate me-2"></i> CLEAR CANVAS
            </button>
        </div>
    </aside>

    <div class="outfit-modal" id="outfitModal">
        <div class="modal-backdrop" onclick="closeSummaryModal()"></div>
        <div class="modal-glass">
            <button class="modal-close" onclick="closeSummaryModal()"><i class="fa-solid fa-xmark"></i></button>
            <h3 class="modal-title">Curation Summary</h3>
            <span class="modal-subtitle">Review your ensemble before securing.</span>

            <div class="modal-items-list" id="modalItemsList">
                </div>

            <div class="modal-footer">
                <div class="dock-row mb-4">
                    <span class="dock-label">FINAL VALUATION</span>
                    <span class="dock-price text-gold">Rs. <span id="modalFinalPrice">0</span></span>
                </div>
                <form id="checkoutForm" method="POST" action="cart-process.php">
                    <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_look">
                    <input type="hidden" name="look_data" id="lookDataInput">
                    <button type="submit" class="btn-vogue-square">
                        <span class="btn-text">SECURE TO CART</span>
                        <span class="btn-border"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

</main>

<?php include '../ReuseableUI/footer.php'; ?>