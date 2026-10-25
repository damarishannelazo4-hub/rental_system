<?php
$grossAll     = 0;
$comboDiscDay = 0;

/* =========================
   SHOP GCASH DETAILS
   (replace with the real details)
========================== */
$gcashNumber = "09XX-XXX-XXXX";
$gcashName   = "Shop Name";

$draftCombos      = draft_combos_get();
$needAll          = draft_need();
$draftColors      = draft_colors_get();
$draftComboColors = draft_combo_colors_get();

$hasDraft = ($draft || $draftCombos);
?>

<aside class="wk-right" id="your-reservation">

<?php if (!$hasDraft): ?>

    <div class="wk-rhead">
        <h3>Your Reservation</h3>
    </div>

    <div class="wk-rscroll">
        <div class="wk-lines">
            <div class="wk-empty">
                No items yet. Use "Add to reservation" on the items, or build a combo.
            </div>
        </div>
    </div>

    <div class="wk-sum">
        <div class="r"><span>Per day (all items)</span><span>₱0.00</span></div>
        <div class="r"><span>Days</span><span>1</span></div>
        <div class="r t"><span>Total</span><span>₱0.00</span></div>
    </div>

    <button type="button" class="btn primary full" disabled>Submit Reservation</button>

<?php else: ?>


<form method="POST"
      action="reserve.php"
      enctype="multipart/form-data"
      id="reservation-form"
      class="wk-rform">

    <input type="hidden" name="action" value="submit">

    <div class="wk-rhead">
        <h3>Your Reservation</h3>
        <button type="button" class="btn tiny" id="clear-all">Clear all</button>
    </div>

    <div class="wk-rscroll">

        <!-- DATES -->

        <div class="wk-two">

            <label class="wk-rlabel">Rental Date
                <input type="date"
                       name="rental_date"
                       id="rental_date"
                       class="wk-field"
                       min="<?= date('Y-m-d') ?>"
                       required>
            </label>

            <label class="wk-rlabel">Return Date
                <input type="date"
                       name="return_date"
                       id="return_date"
                       class="wk-field"
                       min="<?= date('Y-m-d') ?>"
                       required>
            </label>

        </div>


        <!-- PAYMENT + STATUS (status is only shown, the shop sets it) -->

        <div class="wk-two">

            <label class="wk-rlabel">Payment
                <select name="payment_method" id="payment_method" class="wk-field">
                    <option value="Cash">Cash</option>
                    <option value="Online">Online (GCash)</option>
                </select>
            </label>

            <label class="wk-rlabel">Status
                <input type="text"
                       id="pay-status-view"
                       class="wk-field"
                       value="Unpaid"
                       disabled>
            </label>

        </div>


        <!-- ONLINE PAYMENT DETAILS -->

        <div id="online-pay"
             style="display:none; padding:10px; border:1px solid #cfe4fa; border-radius:10px; background:#f4f9ff; font-size:12px;">

            <p style="margin:0 0 4px;">Send payment to GCash:</p>

            <p style="margin:0 0 2px; font-size:17px; font-weight:700; color:#17395f;">
                <?= e($gcashNumber) ?>
            </p>

            <p class="muted-text" style="margin:0 0 8px;">
                <?= e($gcashName) ?>
            </p>

            <p style="margin:0 0 10px;">
                Amount to pay:
                <strong id="pay-amount">
                    ₱<?= number_format($grossAll - $comboDiscDay, 2) ?>
                </strong>
                <span class="muted-text" id="pay-note">
                    (select your rental and return dates first)
                </span>
            </p>

            <label class="wk-rlabel" style="margin-bottom:8px;">
                GCash Reference No.
                <input type="text"
                       name="payment_ref"
                       id="payment_ref"
                       class="wk-field"
                       maxlength="60"
                       placeholder="Reference number from your receipt">
            </label>

            <label class="wk-rlabel">
                Payment Screenshot (JPG/PNG, max 5 MB)
                <input type="file"
                       name="payment_proof"
                       id="payment_proof"
                       accept="image/jpeg,image/png"
                       style="display:block; margin-top:4px; max-width:100%;">
            </label>

            <img id="proof-preview"
                 src=""
                 alt="Payment preview"
                 style="display:none; margin-top:8px; width:100%; max-width:220px; border-radius:10px; border:1px solid #ddd; padding:4px; box-sizing:border-box;">

        </div>


        <!-- CASH NOTE -->

        <p id="cash-note" class="muted-text" style="margin:0; font-size:11px;">
            Cash: you will pay at the shop before picking up your items. Any late penalty will be charged upon return.
        </p>


        <!-- ITEMS AND COMBOS -->

        <div class="wk-lines">

            <?php foreach ($draft as $item_id => $q): ?>

                <?php
                if (!isset($all[$item_id])) {
                    continue;
                }

                $it        = $all[$item_id];
                $available = (int)$it["available_qty"];
                $price     = (float)$it["price_per_day"];
                $sub       = $q * $price;
                $opts      = color_options($it["item_name"]);
                $cur       = $draftColors[$item_id] ?? "";

                $grossAll += $sub;
                ?>

                <div class="wk-line">

                    <div class="wk-lname">

                        <strong><?= e($it["item_name"]) ?></strong>
                        <small>₱<?= number_format($price, 2) ?>/day</small>

                        <?php if ($available < (int)($needAll[$item_id] ?? $q)): ?>
                            <span class="badge danger">Only <?= $available ?> left</span>
                        <?php endif; ?>

                        <?php if ($opts): ?>
                            <select name="color[<?= (int)$item_id ?>]"
                                    class="pl-color"
                                    data-id="<?= (int)$item_id ?>">
                                <option value="">Select color</option>
                                <?php foreach ($opts as $c): ?>
                                    <option value="<?= e($c) ?>" <?= $c === $cur ? "selected" : "" ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                    </div>

                    <input type="number"
                           name="qty[<?= (int)$item_id ?>]"
                           class="pl-pcs"
                           data-id="<?= (int)$item_id ?>"
                           data-price="<?= $price ?>"
                           value="<?= (int)$q ?>"
                           min="1"
                           max="<?= max(1, $available) ?>">

                    <div class="wk-line-sub" id="sub-<?= (int)$item_id ?>">
                        ₱<?= number_format($sub, 2) ?>
                    </div>

                    <button type="button"
                            class="wk-x"
                            data-remove-item="<?= (int)$item_id ?>"
                            title="Remove">×</button>

                </div>

            <?php endforeach; ?>


            <?php foreach ($draftCombos as $n => $citems): ?>

                <?php
                $cGross = 0;

                foreach ($citems as $iid => $q) {
                    if (isset($all[$iid])) {
                        $cGross += $q * (float)$all[$iid]["price_per_day"];
                    }
                }

                $cDisc = combo_discount_amount($cGross);
                $cNet  = $cGross - $cDisc;

                $grossAll     += $cGross;
                $comboDiscDay += $cDisc;
                ?>

                <div class="wk-combo">

                    <div class="wk-grp">
                        <span>
                            Combo <?= (int)$n ?>
                            <span class="badge info">&minus;<?= COMBO_DISCOUNT_PERCENT ?>%</span>
                        </span>

                        <button type="button"
                                class="wk-gx"
                                data-remove-combo="<?= (int)$n ?>">remove combo</button>
                    </div>

                    <?php foreach ($citems as $iid => $q): ?>

                        <?php
                        if (!isset($all[$iid])) {
                            continue;
                        }

                        $it        = $all[$iid];
                        $available = (int)$it["available_qty"];
                        $price     = (float)$it["price_per_day"];
                        $opts      = color_options($it["item_name"]);
                        $cur       = $draftComboColors[$n][$iid] ?? "";
                        ?>

                        <div class="wk-line">

                            <div class="wk-lname">

                                <strong><?= e($it["item_name"]) ?></strong>
                                <small>₱<?= number_format($price, 2) ?>/day</small>

                                <?php if ($available < (int)($needAll[$iid] ?? $q)): ?>
                                    <span class="badge danger">Only <?= $available ?> left</span>
                                <?php endif; ?>

                                <?php if ($opts): ?>
                                    <select name="cc[<?= (int)$n ?>][<?= (int)$iid ?>]"
                                            class="pl-ccolor"
                                            data-combo="<?= (int)$n ?>"
                                            data-id="<?= (int)$iid ?>">
                                        <option value="">Select color</option>
                                        <?php foreach ($opts as $c): ?>
                                            <option value="<?= e($c) ?>" <?= $c === $cur ? "selected" : "" ?>><?= e($c) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>

                            </div>

                            <input type="number"
                                   name="cq[<?= (int)$n ?>][<?= (int)$iid ?>]"
                                   class="pl-cpcs"
                                   data-combo="<?= (int)$n ?>"
                                   data-id="<?= (int)$iid ?>"
                                   data-price="<?= $price ?>"
                                   value="<?= (int)$q ?>"
                                   min="1"
                                   max="<?= max(1, $available) ?>">

                            <div class="wk-line-sub" id="csub-<?= (int)$n ?>-<?= (int)$iid ?>">
                                ₱<?= number_format($q * $price, 2) ?>
                            </div>

                            <span></span>

                        </div>

                    <?php endforeach; ?>

                    <div class="wk-cnote">
                        Combo per day:
                        <strong id="cnet-<?= (int)$n ?>">₱<?= number_format($cNet, 2) ?></strong>
                        <small>(saves <span id="cdisc-<?= (int)$n ?>">₱<?= number_format($cDisc, 2) ?></span>)</small>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <!-- VALID ID -->

        <div class="wk-sec">

            <h4>Valid ID</h4>

            <p class="muted-text" style="margin:0 0 6px; font-size:11px;">
                Upload a clear JPG or PNG, or use your camera. Maximum 5 MB.
            </p>

            <input type="file"
                   id="id_photo"
                   name="id_photo"
                   accept="image/jpeg,image/png"
                   style="display:none;">

            <div style="display:flex; gap:6px; flex-wrap:wrap;">

                <button type="button" class="btn tiny primary" id="upload-btn">
                    <iconify-icon icon="solar:upload-outline" aria-hidden="true"></iconify-icon> Upload ID
                </button>

                <button type="button" class="btn tiny" id="camera-btn">
                    <iconify-icon icon="solar:camera-outline" aria-hidden="true"></iconify-icon> Open Camera
                </button>

            </div>

            <div id="camera-area" style="display:none; margin-top:10px;">

                <video id="camera-video"
                       autoplay
                       playsinline
                       style="width:100%; border-radius:10px; border:1px solid #ddd; background:#000;">
                </video>

                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">

                    <button type="button" class="btn tiny primary" id="take-photo">
                        📸 Take Photo
                    </button>

                    <button type="button" class="btn tiny" id="close-camera">
                        Close Camera
                    </button>

                </div>

            </div>

            <div id="preview-area" style="display:none; margin-top:10px;">

                <p style="margin:0 0 4px; font-size:12px;"><strong>ID Preview:</strong></p>

                <img id="id-preview"
                     src=""
                     alt="ID Preview"
                     style="width:100%; border-radius:10px; border:1px solid #ddd; padding:4px; box-sizing:border-box;">

                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">

                    <button type="button" class="btn tiny primary" id="retake-btn">
                        🔄 Retake / Choose Again
                    </button>

                    <button type="button" class="btn tiny" id="remove-photo">
                        Remove
                    </button>

                </div>

            </div>

            <p id="id-error"
               style="display:none; margin:8px 0 0; font-weight:600; font-size:12px; color:#b6313c;">
            </p>

        </div>

    </div>


    <!-- SUMMARY (per day, days, discount, total) -->

    <div class="wk-sum">

        <div class="r">
            <span>Per day (all items)</span>
            <span id="sum-perday">₱<?= number_format($grossAll, 2) ?></span>
        </div>

        <div class="r">
            <span>Days</span>
            <span id="sum-days">1</span>
        </div>

        <div class="r d" id="sum-discount-row" style="display:<?= $draftCombos ? 'flex' : 'none' ?>;">
            <span>Combo discount (<?= COMBO_DISCOUNT_PERCENT ?>%)</span>
            <span id="sum-discount">-₱<?= number_format($comboDiscDay, 2) ?></span>
        </div>

        <div class="r t">
            <span>Total</span>
            <span id="sum-total">₱<?= number_format($grossAll - $comboDiscDay, 2) ?></span>
        </div>

    </div>

    <button type="submit" class="btn primary full" id="submit-reservation">
        Submit Reservation
    </button>

</form>


<!-- =========================
     RESERVATION SCRIPT
========================== -->

<script>
(function () {

    const reservationForm = document.getElementById("reservation-form");
    const submitBtn       = document.getElementById("submit-reservation");
    const uploadBtn       = document.getElementById("upload-btn");
    const idInput         = document.getElementById("id_photo");
    const cameraBtn       = document.getElementById("camera-btn");
    const cameraArea      = document.getElementById("camera-area");
    const video           = document.getElementById("camera-video");
    const takePhotoBtn    = document.getElementById("take-photo");
    const closeCameraBtn  = document.getElementById("close-camera");
    const previewArea     = document.getElementById("preview-area");
    const preview         = document.getElementById("id-preview");
    const retakeBtn       = document.getElementById("retake-btn");
    const removePhotoBtn  = document.getElementById("remove-photo");
    const errorBox        = document.getElementById("id-error");

    const rentalDate = document.getElementById("rental_date");
    const returnDate = document.getElementById("return_date");
    const payAmount  = document.getElementById("pay-amount");
    const payNote    = document.getElementById("pay-note");

    const COMBO_PERCENT = <?= (int)COMBO_DISCOUNT_PERCENT ?>;
    const TODAY         = "<?= date('Y-m-d') ?>";
    const STORE         = "rsv_form_v1";

    let cameraStream = null;

    let regularGross   = 0;
    let comboGrossByNo = {};


    function peso(n) {
        return "₱" + n.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }


    /* =========================
       SAVE CHANGES IN THE BACKGROUND
       (pcs / color changes are saved to the draft without reloading,
        so the dates, ID and payment you filled in are not lost)
    ========================== */

    let saveChain = Promise.resolve();

    function queueSave(params) {
        saveChain = saveChain.then(function () {
            return fetch("reserve.php", {
                method: "POST",
                body: params,
                credentials: "same-origin",
                redirect: "manual"
            });
        }).catch(function () {});
    }

    function flush() {
        return saveChain;
    }

    function saveItem(id) {

        const qty = document.querySelector('.pl-pcs[data-id="' + id + '"]');
        const col = document.querySelector('.pl-color[data-id="' + id + '"]');

        if (!qty) {
            return;
        }

        /* an item with colors can only be saved once a color is chosen */

        if (col && col.value === "") {
            return;
        }

        const p = new URLSearchParams();

        p.set("action", "update");
        p.set("item_id", id);
        p.set("qty", Math.max(1, parseInt(qty.value, 10) || 1));

        if (col) {
            p.set("color", col.value);
        }

        queueSave(p);
    }

    function saveCombo(n) {

        const p = new URLSearchParams();

        p.set("action", "combo_update");
        p.set("combo", n);

        document.querySelectorAll('.pl-cpcs[data-combo="' + n + '"]').forEach(function (inp) {
            p.set("qty[" + inp.dataset.id + "]", Math.max(1, parseInt(inp.value, 10) || 1));
        });

        document.querySelectorAll('.pl-ccolor[data-combo="' + n + '"]').forEach(function (sel) {
            p.set("color[" + sel.dataset.id + "]", sel.value);
        });

        queueSave(p);
    }

    /* remove / clear: wait for pending saves, then post a normal form */

    function postAction(fields) {

        flush().then(function () {

            const f = document.createElement("form");

            f.method = "POST";
            f.action = "reserve.php";

            Object.keys(fields).forEach(function (k) {
                const i = document.createElement("input");
                i.type  = "hidden";
                i.name  = k;
                i.value = fields[k];
                f.appendChild(i);
            });

            document.body.appendChild(f);
            f.submit();
        });
    }

    document.querySelectorAll("[data-remove-item]").forEach(function (b) {
        b.addEventListener("click", function () {
            postAction({ action: "remove", item_id: b.dataset.removeItem });
        });
    });

    document.querySelectorAll("[data-remove-combo]").forEach(function (b) {
        b.addEventListener("click", function () {
            postAction({ action: "combo_remove", combo: b.dataset.removeCombo });
        });
    });

    document.getElementById("clear-all").addEventListener("click", function () {
        clearStore();
        postAction({ action: "clear" });
    });

    /* adding items from the left side: wait for pending saves first */

    document.querySelectorAll('form[action="reserve.php"]').forEach(function (f) {

        if (f === reservationForm) {
            return;
        }

        f.addEventListener("submit", function (event) {
            event.preventDefault();
            flush().then(function () {
                f.submit();
            });
        });
    });


    /* =========================
       SUMMARY (per day, days, discount, total)
    ========================== */

    function getDays() {

        if (!rentalDate.value || !returnDate.value) {
            return 1;
        }

        const t1 = new Date(rentalDate.value);
        const t2 = new Date(returnDate.value);

        return Math.max(1, Math.round((t2 - t1) / 86400000));
    }

    function updateSummary() {

        const days = getDays();

        let gross     = regularGross;
        let discTotal = 0;

        Object.keys(comboGrossByNo).forEach(function (n) {

            const g = comboGrossByNo[n];

            gross += g;
            discTotal += Math.round(g * days * COMBO_PERCENT) / 100;
        });

        discTotal = Math.round(discTotal * 100) / 100;

        const total = gross * days - discTotal;

        document.getElementById("sum-perday").textContent = peso(gross);
        document.getElementById("sum-days").textContent   = days;

        document.getElementById("sum-discount-row").style.display =
            Object.keys(comboGrossByNo).length ? "flex" : "none";

        document.getElementById("sum-discount").textContent = "-" + peso(discTotal);
        document.getElementById("sum-total").textContent    = peso(total);

        /* GCash amount = same total */

        payAmount.textContent = peso(total);

        if (rentalDate.value && returnDate.value) {
            payNote.textContent = "(" + days + " day" + (days > 1 ? "s" : "") + ")";
        } else {
            payNote.textContent = "(select your rental and return dates first)";
        }
    }

    function recalc() {

        regularGross   = 0;
        comboGrossByNo = {};

        /* ordinary items */

        document.querySelectorAll(".pl-pcs").forEach(function (inp) {

            const id    = inp.dataset.id;
            const price = parseFloat(inp.dataset.price) || 0;
            let   qty   = parseInt(inp.value, 10);

            if (isNaN(qty) || qty < 1) {
                qty = 1;
            }

            const sub = document.getElementById("sub-" + id);

            if (sub) {
                sub.textContent = peso(qty * price);
            }

            regularGross += qty * price;
        });

        /* combos (each combo gets its own discount) */

        document.querySelectorAll(".pl-cpcs").forEach(function (inp) {

            const n     = inp.dataset.combo;
            const id    = inp.dataset.id;
            const price = parseFloat(inp.dataset.price) || 0;
            let   qty   = parseInt(inp.value, 10);

            if (isNaN(qty) || qty < 1) {
                qty = 1;
            }

            const sub = document.getElementById("csub-" + n + "-" + id);

            if (sub) {
                sub.textContent = peso(qty * price);
            }

            comboGrossByNo[n] = (comboGrossByNo[n] || 0) + qty * price;
        });

        Object.keys(comboGrossByNo).forEach(function (n) {

            const gross = comboGrossByNo[n];
            const disc  = Math.round(gross * COMBO_PERCENT) / 100;

            const d = document.getElementById("cdisc-" + n);
            const t = document.getElementById("cnet-" + n);

            if (d) { d.textContent = peso(disc); }
            if (t) { t.textContent = peso(gross - disc); }
        });

        updateSummary();
    }

    document.querySelectorAll(".pl-pcs").forEach(function (inp) {
        inp.addEventListener("input", recalc);
        inp.addEventListener("change", function () {
            recalc();
            saveItem(inp.dataset.id);
        });
    });

    document.querySelectorAll(".pl-color").forEach(function (sel) {
        sel.addEventListener("change", function () {
            saveItem(sel.dataset.id);
        });
    });

    document.querySelectorAll(".pl-cpcs").forEach(function (inp) {
        inp.addEventListener("input", recalc);
        inp.addEventListener("change", function () {
            recalc();
            saveCombo(inp.dataset.combo);
        });
    });

    document.querySelectorAll(".pl-ccolor").forEach(function (sel) {
        sel.addEventListener("change", function () {
            saveCombo(sel.dataset.combo);
        });
    });


    /* SHOW / CLEAR ERROR */

    function showError(message) {
        errorBox.textContent = message;
        errorBox.style.display = "block";
        errorBox.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }

    function clearError() {
        errorBox.textContent = "";
        errorBox.style.display = "none";
    }


    /* STOP CAMERA */

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        video.srcObject = null;
        cameraArea.style.display = "none";
    }


    /* OPEN FILE UPLOAD */

    uploadBtn.addEventListener("click", function () {
        clearError();
        idInput.click();
    });


    /* VALIDATE IMAGE FILE */

    function validateFile(file, label) {

        if (!file) {
            return false;
        }

        const allowedTypes = ["image/jpeg", "image/png"];

        if (!allowedTypes.includes(file.type)) {
            showError("Please use a JPG, JPEG, or PNG image for the " + label + ".");
            return false;
        }

        if (file.size > 5 * 1024 * 1024) {
            showError("The " + label + " must not be larger than 5 MB.");
            return false;
        }

        return true;
    }


    /* FILE UPLOAD (ID) */

    idInput.addEventListener("change", function () {

        clearError();

        const file = this.files[0];

        if (!validateFile(file, "ID photo")) {
            this.value = "";
            previewArea.style.display = "none";
            preview.src = "";
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            preview.src = event.target.result;
            previewArea.style.display = "block";
        };

        reader.readAsDataURL(file);
    });


    /* OPEN CAMERA */

    cameraBtn.addEventListener("click", async function () {

        clearError();

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError("Your browser does not support camera access.");
            return;
        }

        try {

            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: "environment",
                    width:  { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });

            video.srcObject = cameraStream;
            cameraArea.style.display = "block";

        } catch (error) {

            console.error(error);

            showError("Camera access was blocked. Please allow camera permission in your browser and try again.");
        }
    });


    /* TAKE PHOTO */

    takePhotoBtn.addEventListener("click", function () {

        clearError();

        if (!cameraStream) {
            showError("Camera is not active.");
            return;
        }

        const canvas = document.createElement("canvas");

        canvas.width  = video.videoWidth  || 1280;
        canvas.height = video.videoHeight || 720;

        const context = canvas.getContext("2d");

        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(function (blob) {

            if (!blob) {
                showError("Unable to capture the photo.");
                return;
            }

            const file = new File([blob], "id_camera.jpg", { type: "image/jpeg" });

            /* Put camera image into the hidden file input */

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            idInput.files = dataTransfer.files;

            /* Show preview */

            preview.src = URL.createObjectURL(blob);
            previewArea.style.display = "block";

            /* Stop webcam after capture */

            stopCamera();

        }, "image/jpeg", 0.90);
    });


    /* CLOSE CAMERA */

    closeCameraBtn.addEventListener("click", function () {
        stopCamera();
    });


    /* RETAKE / REMOVE */

    function resetIdPhoto() {
        idInput.value = "";
        preview.src = "";
        previewArea.style.display = "none";
        clearError();
    }

    retakeBtn.addEventListener("click", resetIdPhoto);
    removePhotoBtn.addEventListener("click", resetIdPhoto);


    /* =========================
       PAYMENT (dropdown: Cash / Online)
    ========================== */

    const paySelect  = document.getElementById("payment_method");
    const payStatus  = document.getElementById("pay-status-view");
    const onlineBox  = document.getElementById("online-pay");
    const cashNote   = document.getElementById("cash-note");
    const proofInput = document.getElementById("payment_proof");
    const proofPrev  = document.getElementById("proof-preview");
    const refInput   = document.getElementById("payment_ref");

    function isOnline() {
        return paySelect.value === "Online";
    }

    function updatePayment() {

        const online = isOnline();

        onlineBox.style.display = online ? "block" : "none";
        cashNote.style.display  = online ? "none"  : "block";

        /* only shown: the shop sets the real payment status */

        payStatus.value = online ? "For Verification" : "Unpaid";
    }


    /* Payment screenshot preview */

    proofInput.addEventListener("change", function () {

        clearError();

        const file = this.files[0];

        if (!file) {
            proofPrev.style.display = "none";
            return;
        }

        if (!validateFile(file, "payment screenshot")) {
            this.value = "";
            proofPrev.style.display = "none";
            return;
        }

        proofPrev.src = URL.createObjectURL(file);
        proofPrev.style.display = "block";
    });


    /* =========================
       KEEP DATES + PAYMENT WHEN THE PAGE RELOADS
       (adding an item reloads the page)
    ========================== */

    function saveStore() {
        try {
            sessionStorage.setItem(STORE, JSON.stringify({
                rd:  rentalDate.value,
                ret: returnDate.value,
                pay: isOnline() ? "Online" : "Cash",
                ref: refInput.value
            }));
        } catch (e) {}
    }

    function clearStore() {
        try {
            sessionStorage.removeItem(STORE);
        } catch (e) {}
    }

    function applyReturnMin() {

        if (!rentalDate.value) {
            return;
        }

        const next = new Date(rentalDate.value);
        next.setDate(next.getDate() + 1);

        const iso = next.toISOString().slice(0, 10);

        returnDate.min = iso;

        if (returnDate.value && returnDate.value < iso) {
            returnDate.value = "";
        }
    }

    function restoreStore() {

        try {

            const s = JSON.parse(sessionStorage.getItem(STORE) || "null");

            if (!s) {
                return;
            }

            if (s.rd && s.rd >= TODAY) {

                rentalDate.value = s.rd;
                applyReturnMin();

                if (s.ret && s.ret > s.rd) {
                    returnDate.value = s.ret;
                }
            }

            if (s.pay === "Online") {
                paySelect.value = "Online";
            }

            if (s.ref) {
                refInput.value = s.ref;
            }

        } catch (e) {}
    }

    rentalDate.addEventListener("change", function () {
        applyReturnMin();
        updateSummary();
        saveStore();
    });

    returnDate.addEventListener("change", function () {
        updateSummary();
        saveStore();
    });

    paySelect.addEventListener("change", function () {
        updatePayment();
        saveStore();
    });

    refInput.addEventListener("input", saveStore);


    /* =========================
       VALIDATE BEFORE SUBMIT
    ========================== */

    reservationForm.addEventListener("submit", function (event) {

        event.preventDefault();

        clearError();

        /* Make sure the latest pcs are shown */

        recalc();

        /* ID is required */

        if (!idInput.files || idInput.files.length === 0) {
            showError("Please upload or take a photo of your valid ID first.");
            return;
        }

        if (!validateFile(idInput.files[0], "ID photo")) {
            return;
        }

        /* Every item that has colors needs one */

        const emptyColor = Array.from(
            document.querySelectorAll(".pl-color, .pl-ccolor")
        ).find(function (sel) {
            return sel.value === "";
        });

        if (emptyColor) {
            showError("Please select a color for every item that has color options.");
            emptyColor.focus();
            return;
        }

        /* Online payment needs reference + screenshot */

        if (isOnline()) {

            if (refInput.value.trim() === "") {
                showError("Please enter the GCash reference number.");
                refInput.focus();
                return;
            }

            if (!proofInput.files || proofInput.files.length === 0) {
                showError("Please upload the screenshot of your GCash payment.");
                return;
            }

            if (!validateFile(proofInput.files[0], "payment screenshot")) {
                return;
            }
        }

        /* All good: wait for pending saves, then send (blocks a second click) */

        clearStore();

        submitBtn.disabled = true;

        flush().then(function () {
            reservationForm.submit();
        });
    });

    /* Back button: make the button usable again */

    window.addEventListener("pageshow", function () {
        submitBtn.disabled = false;
    });


    /* Stop camera when leaving page */

    window.addEventListener("beforeunload", function () {
        stopCamera();
    });


    /* First setup when the page loads */

    restoreStore();
    updatePayment();
    recalc();

})();
</script>


<?php endif; ?>

</aside>