/**
 * Velvet Vogue - The Final Master Arcade Engine (State Machine Update)
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    const AudioContext = window.AudioContext || window.webkitAudioContext;
    let audioCtx = null;

    const isTouchDevice =
        ("ontouchstart" in window) ||
        navigator.maxTouchPoints > 0;

    const reducedMotion = window
        .matchMedia("(prefers-reduced-motion: reduce)")
        .matches;

    const lowPowerDevice =
        reducedMotion ||
        (Number.isFinite(navigator.deviceMemory) &&
            navigator.deviceMemory <= 4) ||
        (Number.isFinite(navigator.hardwareConcurrency) &&
            navigator.hardwareConcurrency <= 4);

    const particleCount = lowPowerDevice ? 2 : 4;
    const maxActiveItems = lowPowerDevice ? 10 : 18;
    const moneyFormatter = new Intl.NumberFormat();

    if (reducedMotion) {
        document.body.classList.add("vv-low-power");
    }

    function ensureAudioContext() {
        if (!AudioContext) {
            return null;
        }

        try {
            if (!audioCtx) {
                audioCtx = new AudioContext();
            }

            if (audioCtx.state === "suspended") {
                audioCtx.resume().catch(() => {});
            }

            return audioCtx;
        } catch {
            return null;
        }
    }

    // =====================================================
    // AUDIO ENGINE
    // =====================================================

    function playCrashSound(isHeavy = false) {
        const context = ensureAudioContext();

        if (!context) {
            return;
        }

        const bufferSize =
            context.sampleRate * (isHeavy ? 1.2 : 0.6);

        const buffer = context.createBuffer(
            1,
            bufferSize,
            context.sampleRate
        );

        const data = buffer.getChannelData(0);

        for (let i = 0; i < bufferSize; i++) {
            data[i] = Math.random() * 2 - 1;
        }

        const noise = context.createBufferSource();
        noise.buffer = buffer;

        const filter = context.createBiquadFilter();
        filter.type = "lowpass";
        filter.frequency.value = isHeavy ? 300 : 800;

        const envelope = context.createGain();

        envelope.gain.setValueAtTime(
            isHeavy ? 4 : 1.5,
            context.currentTime
        );

        envelope.gain.exponentialRampToValueAtTime(
            0.01,
            context.currentTime + (isHeavy ? 1.2 : 0.5)
        );

        noise.connect(filter);
        filter.connect(envelope);
        envelope.connect(context.destination);

        noise.start();
    }

    function playSuccessSound(isGodMode = false) {
        const context = ensureAudioContext();

        if (!context) {
            return;
        }

        const oscillator = context.createOscillator();
        const gainNode = context.createGain();

        oscillator.type = isGodMode ? "square" : "sine";

        oscillator.frequency.setValueAtTime(
            isGodMode ? 400 : 800,
            context.currentTime
        );

        oscillator.frequency.exponentialRampToValueAtTime(
            isGodMode ? 800 : 1200,
            context.currentTime + 0.1
        );

        gainNode.gain.setValueAtTime(
            0,
            context.currentTime
        );

        gainNode.gain.linearRampToValueAtTime(
            1,
            context.currentTime + 0.05
        );

        gainNode.gain.exponentialRampToValueAtTime(
            0.01,
            context.currentTime + 0.5
        );

        oscillator.connect(gainNode);
        gainNode.connect(context.destination);

        oscillator.start();
        oscillator.stop(context.currentTime + 0.5);
    }

    function playCatchSound() {
        const context = ensureAudioContext();

        if (!context) {
            return;
        }

        const oscillator = context.createOscillator();
        const gainNode = context.createGain();

        oscillator.type = "sine";

        oscillator.frequency.setValueAtTime(
            600,
            context.currentTime
        );

        oscillator.frequency.exponentialRampToValueAtTime(
            1200,
            context.currentTime + 0.1
        );

        gainNode.gain.setValueAtTime(
            0,
            context.currentTime
        );

        gainNode.gain.linearRampToValueAtTime(
            0.3,
            context.currentTime + 0.02
        );

        gainNode.gain.exponentialRampToValueAtTime(
            0.01,
            context.currentTime + 0.15
        );

        oscillator.connect(gainNode);
        gainNode.connect(context.destination);

        oscillator.start();
        oscillator.stop(context.currentTime + 0.15);
    }

    // =====================================================
    // INTRO SEQUENCE
    // =====================================================

    const crashScreen =
        document.getElementById("truckCrashOverlay");

    const revealPage = () => {
        document.body.classList.remove(
            "vv-404-intro-active"
        );

        document.body.classList.add(
            "vv-404-ready"
        );
    };

    if (crashScreen) {
        let introFinished = false;
        let fallbackTimer = 0;

        const finishIntro = () => {
            if (introFinished) {
                return;
            }

            introFinished = true;

            window.clearTimeout(fallbackTimer);

            revealPage();

            crashScreen.classList.add("is-exiting");

            window.setTimeout(() => {
                crashScreen.remove();
            }, 420);
        };

        if (reducedMotion) {
            crashScreen.remove();
            revealPage();
        } else {
            const finalLogoPiece =
                crashScreen.querySelector(".sl-8");

            finalLogoPiece?.addEventListener(
                "animationend",
                finishIntro,
                { once: true }
            );

            fallbackTimer = window.setTimeout(
                finishIntro,
                6200
            );
        }
    } else {
        revealPage();
    }

    // =====================================================
    // GPU-FRIENDLY 3D SCENE AND GOD MODE
    // =====================================================

    const parallaxContainer =
        document.getElementById("parallaxContainer");

    let godMode = false;
    let cheatBuffer = "";

    let parallaxFrame = 0;
    let pointerX = window.innerWidth / 2;
    let pointerY = window.innerHeight / 2;

    function resetVrScene() {
        if (!parallaxContainer) {
            return;
        }

        parallaxContainer.style.setProperty(
            "--vv-vr-x",
            "0deg"
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-y",
            "0deg"
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-shift-x",
            "0px"
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-shift-y",
            "0px"
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-glare-x",
            "50%"
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-glare-y",
            "42%"
        );

        document.body.classList.remove(
            "vv-vr-active"
        );
    }

    function renderVrScene() {
        parallaxFrame = 0;

        if (
            !parallaxContainer ||
            gameActive ||
            reducedMotion ||
            document.hidden ||
            document.body.classList.contains(
                "vv-404-intro-active"
            )
        ) {
            return;
        }

        const width = Math.max(
            1,
            window.innerWidth
        );

        const height = Math.max(
            1,
            window.innerHeight
        );

        const normalizedX = Math.max(
            -1,
            Math.min(
                1,
                (pointerX / width - 0.5) * 2
            )
        );

        const normalizedY = Math.max(
            -1,
            Math.min(
                1,
                (pointerY / height - 0.5) * 2
            )
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-x",
            `${(-normalizedY * 6.5).toFixed(2)}deg`
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-y",
            `${(normalizedX * 9).toFixed(2)}deg`
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-shift-x",
            `${(normalizedX * 8).toFixed(2)}px`
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-shift-y",
            `${(normalizedY * 5).toFixed(2)}px`
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-glare-x",
            `${((normalizedX + 1) * 50).toFixed(1)}%`
        );

        parallaxContainer.style.setProperty(
            "--vv-vr-glare-y",
            `${((normalizedY + 1) * 50).toFixed(1)}%`
        );

        document.body.classList.add(
            "vv-vr-active"
        );
    }

    function queueVrScene(event) {
        if (
            !parallaxContainer ||
            gameActive ||
            reducedMotion ||
            document.body.classList.contains(
                "vv-404-intro-active"
            )
        ) {
            return;
        }

        if (
            event.pointerType &&
            event.pointerType !== "mouse" &&
            event.pointerType !== "pen"
        ) {
            return;
        }

        pointerX = event.clientX;
        pointerY = event.clientY;

        if (!parallaxFrame) {
            parallaxFrame =
                requestAnimationFrame(renderVrScene);
        }
    }

    document.addEventListener(
        "pointermove",
        queueVrScene,
        { passive: true }
    );

    document.addEventListener(
        "pointerleave",
        resetVrScene,
        { passive: true }
    );

    window.addEventListener(
        "blur",
        resetVrScene
    );

    document.addEventListener(
        "keydown",
        (event) => {
            if (gameActive || godMode) {
                return;
            }

            cheatBuffer += event.key.toLowerCase();

            if (cheatBuffer.length > 5) {
                cheatBuffer =
                    cheatBuffer.substring(1);
            }

            if (cheatBuffer === "vogue") {
                activateGodMode();
            }
        }
    );

    function activateGodMode() {
        godMode = true;

        document.body.classList.add(
            "god-mode"
        );

        badTrash = [
            "💎",
            "💰",
            "👑"
        ];

        livesVal.innerText = "♾️";
        lives = 999;

        playSuccessSound(true);

        const alert =
            document.getElementById("overrideAlert");

        if (alert) {
            alert.style.display = "flex";

            setTimeout(() => {
                alert.style.display = "none";
            }, 3000);
        }
    }

    // =====================================================
    // GAME ENGINE SETUP
    // =====================================================

    const gameZone =
        document.getElementById("gameZone");

    const playerCart =
        document.getElementById("playerCart");

    const scoreVal =
        document.getElementById("scoreVal");

    const livesVal =
        document.getElementById("livesVal");

    const overlay =
        document.getElementById("goofyOverlay");

    const goofyTitle =
        document.getElementById("goofyTitle");

    const goofyDesc =
        document.getElementById("goofyDesc");

    const btnStart =
        document.getElementById("startGoofyGame");

    const btnCashOut =
        document.getElementById("btnCashOut");

    const leaderboardOverlay =
        document.getElementById("leaderboardOverlay");

    const btnShowLeaderboard =
        document.getElementById("btnShowLeaderboard");

    const btnCloseLeaderboard =
        document.getElementById("btnCloseLeaderboard");

    const leaderboardInputUI =
        document.getElementById("leaderboardInputUI");

    const playerNameInput =
        document.getElementById("playerNameInput");

    const couponOverlay =
        document.getElementById("couponOverlay");

    const goodMerch = [
        "👗",
        "👠",
        "👜",
        "🕶️",
        "🧥",
        "👔"
    ];

    let badTrash = [
        "💣",
        "🛑",
        "☢️"
    ];

    let score = 0;
    let lives = 3;

    let gameActive = false;
    let dropSpeed = 3;
    let spawnRate = 1000;

    let itemsArray = [];
    let spawnerInterval;
    let loopId;

    let hasCaughtCoupon = false;
    let isGameOver = false;

    let lastFrameTime = 0;
    let leaderboardLoaded = false;

    let gameWidth =
        gameZone.offsetWidth;

    let gameHeight =
        gameZone.offsetHeight;

    let cartWidth = 50;
    let itemSize = 40;

    let cartX =
        gameWidth / 2 -
        cartWidth / 2;

    playerCart.style.transform =
        `translate3d(${cartX}px, 0, 0)`;

    const updateGameBounds = () => {
        gameWidth =
            gameZone.offsetWidth;

        gameHeight =
            gameZone.offsetHeight;

        if (
            cartX >
            gameWidth - cartWidth
        ) {
            cartX = Math.max(
                0,
                gameWidth - cartWidth
            );
        }
    };

    if ("ResizeObserver" in window) {
        new ResizeObserver(
            updateGameBounds
        ).observe(gameZone);
    } else {
        window.addEventListener(
            "resize",
            updateGameBounds,
            { passive: true }
        );
    }

    gameZone.requestPointerLock =
        gameZone.requestPointerLock ||
        gameZone.mozRequestPointerLock;

    document.exitPointerLock =
        document.exitPointerLock ||
        document.mozExitPointerLock;

    function updateCartMovement(event) {
        if (!gameActive) {
            return;
        }

        cartX +=
            event.movementX * 1.5;

        if (cartX < 0) {
            cartX = 0;
        }

        if (
            cartX >
            gameWidth - cartWidth
        ) {
            cartX =
                gameWidth - cartWidth;
        }

        playerCart.style.transform =
            `translate3d(${cartX}px, 0, 0)`;
    }

    // =====================================================
    // STRICT STATE MACHINE
    // =====================================================

    document.addEventListener(
        "pointerlockchange",
        lockChangeAlert,
        false
    );

    function lockChangeAlert() {
        if (
            document.pointerLockElement ===
            gameZone
        ) {
            document.addEventListener(
                "mousemove",
                updateCartMovement,
                false
            );

            resetVrScene();

            if (
                isGameOver ||
                (lives <= 0 && !godMode)
            ) {
                resetGameStats();
            }

            resumeGameLogic();
        } else {
            document.removeEventListener(
                "mousemove",
                updateCartMovement,
                false
            );

            if (gameActive) {
                pauseGameLogic();
            }
        }
    }

    gameZone.addEventListener(
        "touchmove",
        (event) => {
            if (!gameActive) {
                return;
            }

            event.preventDefault();

            const touchX =
                event.touches[0].clientX -
                gameZone
                    .getBoundingClientRect()
                    .left;

            cartX =
                touchX -
                cartWidth / 2;

            if (cartX < 0) {
                cartX = 0;
            }

            if (
                cartX >
                gameWidth - cartWidth
            ) {
                cartX =
                    gameWidth - cartWidth;
            }

            playerCart.style.transform =
                `translate3d(${cartX}px, 0, 0)`;
        },
        { passive: false }
    );

    function resetGameStats() {
        score = 0;

        if (!godMode) {
            lives = 3;
            livesVal.innerText = "❤️❤️❤️";
        }

        dropSpeed = 3;
        spawnRate = 1000;
        hasCaughtCoupon = false;

        scoreVal.innerText = "$0";

        itemsArray.forEach((item) => {
            item.element.remove();
        });

        itemsArray = [];

        isGameOver = false;

        leaderboardInputUI.style.display =
            "none";

        btnCashOut.style.display =
            "none";
    }

    function resumeGameLogic() {
        gameActive = true;

        overlay.style.setProperty(
            "display",
            "none",
            "important"
        );

        leaderboardOverlay.style.setProperty(
            "display",
            "none",
            "important"
        );

        couponOverlay.style.setProperty(
            "display",
            "none",
            "important"
        );

        clearInterval(spawnerInterval);

        spawnerInterval =
            setInterval(
                spawnItem,
                spawnRate
            );

        cancelAnimationFrame(loopId);

        lastFrameTime =
            performance.now();

        loopId =
            requestAnimationFrame(gameLoop);
    }

    function pauseGameLogic() {
        gameActive = false;

        clearInterval(spawnerInterval);
        cancelAnimationFrame(loopId);

        if (
            !isGameOver &&
            couponOverlay.style.display !==
                "flex" &&
            leaderboardOverlay.style.display !==
                "flex"
        ) {
            goofyTitle.innerText =
                "SYSTEM IDLE";

            goofyTitle.style.color =
                "#fff";

            goofyDesc.innerText =
                "CLICK BUTTON TO RE-LOCK MOUSE & RESUME";

            btnStart.innerText =
                "RESUME PROTOCOL";

            leaderboardInputUI.style.display =
                "none";

            if (score > 0) {
                btnCashOut.style.display =
                    "block";
            }

            overlay.style.setProperty(
                "display",
                "flex",
                "important"
            );
        }
    }

    // =====================================================
    // SPAWNING, PHYSICS AND EFFECTS
    // =====================================================

    function spawnItem() {
        if (
            !gameActive ||
            itemsArray.length >= maxActiveItems
        ) {
            return;
        }

        const randomValue = Math.random();

        let type = "good";

        const element =
            document.createElement("div");

        element.className =
            "falling-item";

        /*
         * Prevent two coupons from appearing at once.
         * A new coupon can appear after the previous
         * coupon falls out of the game zone.
         */
        const couponAlreadyFalling =
            itemsArray.some(
                (item) =>
                    item.type === "key"
            );

        if (
            randomValue > 0.96 &&
            !hasCaughtCoupon &&
            !couponAlreadyFalling
        ) {
            type = "key";

            /*
             * Do not add falling-key to the parent.
             *
             * The parent uses transform: translate3d()
             * for falling movement. The falling-key
             * CSS animation also changes transform.
             *
             * Putting falling-key on the child allows
             * the ticket to pulse while the parent falls.
             */
            element.classList.add(
                "coupon-item"
            );

            const couponIcon =
                document.createElement("span");

            couponIcon.className =
                "falling-key";

            couponIcon.textContent =
                "🎫";

            element.appendChild(
                couponIcon
            );
        } else if (
            randomValue > 0.80
        ) {
            type = "bad";

            element.textContent =
                badTrash[
                    Math.floor(
                        Math.random() *
                        badTrash.length
                    )
                ];
        } else {
            type = "good";

            element.textContent =
                goodMerch[
                    Math.floor(
                        Math.random() *
                        goodMerch.length
                    )
                ];
        }

        const availableWidth =
            Math.max(
                1,
                gameWidth - itemSize
            );

        const startX =
            Math.floor(
                Math.random() *
                availableWidth
            );

        const startY = -60;

        element.style.transform =
            `translate3d(${startX}px, ${startY}px, 0)`;

        gameZone.appendChild(element);

        itemsArray.push({
            element: element,
            x: startX,
            y: startY,
            type: type
        });
    }

    function createFloatingText(
        x,
        y,
        text,
        color
    ) {
        const effect =
            document.createElement("div");

        effect.className = "float-fx";
        effect.innerText = text;
        effect.style.color = color;

        effect.style.setProperty(
            "--startX",
            `${x}px`
        );

        effect.style.setProperty(
            "--startY",
            `${y}px`
        );

        gameZone.appendChild(effect);

        setTimeout(() => {
            effect.remove();
        }, 1000);
    }

    function spawnParticles(
        x,
        y,
        color
    ) {
        const fragment =
            document.createDocumentFragment();

        const particles = [];

        for (
            let i = 0;
            i < particleCount;
            i++
        ) {
            const particle =
                document.createElement("div");

            particle.className =
                "catch-particle";

            particle.style.backgroundColor =
                color;

            particle.style.left =
                x +
                itemSize / 2 +
                "px";

            particle.style.top =
                y +
                itemSize / 2 +
                "px";

            const angle =
                (i / particleCount) *
                Math.PI *
                2;

            const velocity =
                Math.random() * 30 + 20;

            particle.style.setProperty(
                "--tX",
                Math.cos(angle) *
                    velocity +
                    "px"
            );

            particle.style.setProperty(
                "--tY",
                Math.sin(angle) *
                    velocity +
                    "px"
            );

            fragment.appendChild(
                particle
            );

            particles.push(
                particle
            );
        }

        gameZone.appendChild(fragment);

        window.setTimeout(() => {
            particles.forEach(
                (particle) => {
                    particle.remove();
                }
            );
        }, 500);
    }

    function injectCouponToDatabase() {
        const formData =
            new FormData();

        formData.append(
            "generate_404",
            "true"
        );

        fetch(
            "../Actions/apply_coupon.php",
            {
                method: "POST",
                body: formData
            }
        )
            .then(async (response) => ({
                response: response,
                data: await response
                    .json()
                    .catch(() => ({}))
            }))
            .then(
                ({
                    response,
                    data
                }) => {
                    const couponDisplay =
                        document.getElementById(
                            "generatedCouponCode"
                        );

                    if (!couponDisplay) {
                        return;
                    }

                    if (
                        response.ok &&
                        data.status === "success"
                    ) {
                        couponDisplay.innerText =
                            data.code;

                        return;
                    }

                    couponDisplay.innerText =
                        response.status === 401
                            ? "SIGN IN TO CLAIM"
                            : "TRY AGAIN LATER";
                }
            )
            .catch(() => {
                const couponDisplay =
                    document.getElementById(
                        "generatedCouponCode"
                    );

                if (couponDisplay) {
                    couponDisplay.innerText =
                        "TRY AGAIN LATER";
                }
            });
    }

    function gameLoop(timestamp) {
        if (!gameActive) {
            return;
        }

        const elapsed =
            Math.min(
                40,
                Math.max(
                    8,
                    timestamp -
                        lastFrameTime
                )
            );

        const frameScale =
            elapsed / 16.667;

        lastFrameTime =
            timestamp;

        const cartTopBound =
            gameHeight - 60;

        for (
            let i =
                itemsArray.length - 1;
            i >= 0;
            i--
        ) {
            const item =
                itemsArray[i];

            item.y +=
                dropSpeed *
                frameScale;

            item.element.style.transform =
                `translate3d(${item.x}px, ${item.y}px, 0)`;

            const reachesCart =
                item.y + itemSize >=
                    cartTopBound &&
                item.y <=
                    gameHeight - 10;

            const overlapsCart =
                item.x + itemSize >
                    cartX &&
                item.x <
                    cartX + cartWidth;

            if (
                reachesCart &&
                overlapsCart
            ) {
                if (
                    item.type === "good"
                ) {
                    const points =
                        godMode
                            ? 10000
                            : 1000;

                    score += points;

                    scoreVal.innerText =
                        "$" +
                        moneyFormatter.format(
                            score
                        );

                    createFloatingText(
                        item.x,
                        item.y,
                        `+$${points / 1000}K`,
                        godMode
                            ? "#0ff"
                            : "#D4AF37"
                    );

                    spawnParticles(
                        item.x,
                        item.y,
                        godMode
                            ? "#0ff"
                            : "#D4AF37"
                    );

                    playCatchSound();

                    if (
                        score % 10000 ===
                            0 &&
                        !godMode
                    ) {
                        dropSpeed += 0.1;

                        clearInterval(
                            spawnerInterval
                        );

                        spawnRate =
                            Math.max(
                                250,
                                spawnRate - 100
                            );

                        spawnerInterval =
                            setInterval(
                                spawnItem,
                                spawnRate
                            );
                    }
                } else if (
                    item.type === "key"
                ) {
                    hasCaughtCoupon = true;

                    createFloatingText(
                        item.x,
                        item.y,
                        "KEY SECURED!",
                        "#fff"
                    );

                    playSuccessSound();

                    gameActive = false;

                    clearInterval(
                        spawnerInterval
                    );

                    cancelAnimationFrame(
                        loopId
                    );

                    overlay.style.setProperty(
                        "display",
                        "none",
                        "important"
                    );

                    couponOverlay.style.setProperty(
                        "display",
                        "flex",
                        "important"
                    );

                    if (
                        document.pointerLockElement ===
                        gameZone
                    ) {
                        document.exitPointerLock();
                    }

                    injectCouponToDatabase();

                    item.element.remove();

                    itemsArray.splice(
                        i,
                        1
                    );

                    return;
                } else {
                    if (godMode) {
                        score += 10000;

                        scoreVal.innerText =
                            "$" +
                            moneyFormatter.format(
                                score
                            );

                        createFloatingText(
                            item.x,
                            item.y,
                            "+$10K",
                            "#0ff"
                        );

                        spawnParticles(
                            item.x,
                            item.y,
                            "#0ff"
                        );

                        playCatchSound();
                    } else {
                        lives--;

                        const remainingLives =
                            Math.max(
                                0,
                                lives
                            );

                        livesVal.innerText =
                            "❤️".repeat(
                                remainingLives
                            ) +
                            "🖤".repeat(
                                3 -
                                    remainingLives
                            );

                        createFloatingText(
                            item.x,
                            item.y,
                            "CRASH!",
                            "#ff4d4d"
                        );

                        spawnParticles(
                            item.x,
                            item.y,
                            "#ff4d4d"
                        );

                        playCrashSound(false);

                        gameZone.classList.remove(
                            "shake"
                        );

                        void gameZone.offsetWidth;

                        gameZone.classList.add(
                            "shake"
                        );

                        if (lives <= 0) {
                            endGameTrigger();
                            return;
                        }
                    }
                }

                item.element.remove();

                itemsArray.splice(
                    i,
                    1
                );

                continue;
            }

            /*
             * Remove missed items.
             *
             * When a coupon is removed here, it is no
             * longer in itemsArray. This means another
             * coupon may spawn later.
             */
            if (item.y > gameHeight) {
                item.element.remove();

                itemsArray.splice(
                    i,
                    1
                );
            }
        }

        loopId =
            requestAnimationFrame(gameLoop);
    }

    // =====================================================
    // LEADERBOARD AJAX LOGIC
    // =====================================================

    function fetchLeaderboard(
        force = false
    ) {
        const list =
            document.getElementById(
                "leaderboardList"
            );

        if (
            !list ||
            (leaderboardLoaded && !force)
        ) {
            return;
        }

        fetch(
            "../Actions/leaderboard_action.php?action=fetch",
            {
                cache: "no-store"
            }
        )
            .then((response) =>
                response.json()
            )
            .then((data) => {
                list.replaceChildren();

                leaderboardLoaded =
                    data.status ===
                    "success";

                if (
                    data.status ===
                        "success" &&
                    Array.isArray(
                        data.leaderboard
                    ) &&
                    data.leaderboard.length >
                        0
                ) {
                    data.leaderboard.forEach(
                        (
                            entry,
                            index
                        ) => {
                            const item =
                                document.createElement(
                                    "li"
                                );

                            const rankClass =
                                index === 0
                                    ? "rank-1"
                                    : index === 1
                                      ? "rank-2"
                                      : index ===
                                          2
                                        ? "rank-3"
                                        : "";

                            item.className =
                                `leaderboard-item ${rankClass}`;

                            const rank =
                                document.createElement(
                                    "span"
                                );

                            rank.className =
                                "lb-rank";

                            rank.textContent =
                                String(
                                    index + 1
                                ).padStart(
                                    2,
                                    "0"
                                );

                            const name =
                                document.createElement(
                                    "span"
                                );

                            name.className =
                                "lb-name";

                            const displayName =
                                String(
                                    entry.playerName ||
                                        "ANONYMOUS"
                                );

                            if (
                                displayName.startsWith(
                                    "[VOG]"
                                )
                            ) {
                                const tag =
                                    document.createElement(
                                        "span"
                                    );

                                tag.className =
                                    "god-tag";

                                tag.textContent =
                                    "[VOG]";

                                name.append(
                                    tag,
                                    document.createTextNode(
                                        displayName
                                            .replace(
                                                "[VOG]",
                                                ""
                                            )
                                            .trim()
                                    )
                                );
                            } else {
                                name.textContent =
                                    displayName;
                            }

                            const scoreValue =
                                Number.parseInt(
                                    entry.score,
                                    10
                                );

                            const scoreNode =
                                document.createElement(
                                    "span"
                                );

                            scoreNode.className =
                                "lb-score";

                            scoreNode.textContent =
                                `$${Number.isFinite(scoreValue)
                                    ? moneyFormatter.format(scoreValue)
                                    : "0"}`;

                            item.append(
                                rank,
                                name,
                                scoreNode
                            );

                            list.appendChild(
                                item
                            );
                        }
                    );
                } else {
                    const empty =
                        document.createElement(
                            "li"
                        );

                    empty.className =
                        "text-center text-muted py-4";

                    empty.textContent =
                        "NO RECORDS FOUND. BE THE FIRST.";

                    list.appendChild(
                        empty
                    );
                }
            })
            .catch(() => {
                const error =
                    document.createElement(
                        "li"
                    );

                error.className =
                    "text-danger text-center";

                error.textContent =
                    "UPLINK FAILED";

                list.replaceChildren(
                    error
                );
            });
    }

    function saveScore() {
        if (score === 0) {
            triggerLockOrTouchStart();
            return;
        }

        let playerName =
            playerNameInput.value.trim();

        if (playerName === "") {
            playerName = "ANONYMOUS";
        }

        if (godMode) {
            playerName =
                `[VOG] ${playerName}`.substring(
                    0,
                    15
                );
        }

        const formData =
            new FormData();

        formData.append(
            "action",
            "save"
        );

        formData.append(
            "player_name",
            playerName
        );

        formData.append(
            "score",
            score
        );

        btnStart.innerText =
            "UPLOADING...";

        btnCashOut.innerText =
            "UPLOADING...";

        fetch(
            "../Actions/leaderboard_action.php",
            {
                method: "POST",
                body: formData
            }
        )
            .then(() => {
                fetchLeaderboard(true);

                score = 0;

                btnStart.innerText =
                    "REBOOT PROTOCOL";

                leaderboardInputUI.style.display =
                    "none";
            })
            .catch(() => {
                btnStart.innerText =
                    "REBOOT PROTOCOL";
            });
    }

    // =====================================================
    // UI BUTTON TRIGGERS
    // =====================================================

    function triggerLockOrTouchStart() {
        if (isTouchDevice) {
            if (
                isGameOver ||
                (lives <= 0 && !godMode)
            ) {
                resetGameStats();
            }

            resumeGameLogic();
        } else {
            gameZone.requestPointerLock();
        }
    }

    function handleStartClick() {
        ensureAudioContext();

        if (
            isGameOver &&
            score > 0
        ) {
            saveScore();
        } else {
            triggerLockOrTouchStart();
        }
    }

    function endGameTrigger() {
        gameActive = false;
        isGameOver = true;

        clearInterval(
            spawnerInterval
        );

        cancelAnimationFrame(
            loopId
        );

        goofyTitle.innerText =
            "CRITICAL FAILURE";

        goofyTitle.style.color =
            "#ff4d4d";

        goofyDesc.innerText =
            "FINAL YIELD: $" +
            moneyFormatter.format(
                score
            );

        if (score > 0) {
            leaderboardInputUI.style.display =
                "block";

            btnStart.innerText =
                "SUBMIT SCORE & REBOOT";

            if (
                playerNameInput &&
                !playerNameInput.readOnly
            ) {
                playerNameInput.focus();
            }
        } else {
            leaderboardInputUI.style.display =
                "none";

            btnStart.innerText =
                "REBOOT PROTOCOL";
        }

        btnCashOut.style.display =
            "none";

        overlay.style.setProperty(
            "display",
            "flex",
            "important"
        );

        if (
            document.pointerLockElement ===
            gameZone
        ) {
            document.exitPointerLock();
        }
    }

    if (btnStart) {
        btnStart.addEventListener(
            "click",
            handleStartClick
        );
    }

    if (btnCashOut) {
        btnCashOut.addEventListener(
            "click",
            () => {
                endGameTrigger();
            }
        );
    }

    if (playerNameInput) {
        playerNameInput.addEventListener(
            "keypress",
            (event) => {
                if (
                    event.key === "Enter"
                ) {
                    handleStartClick();
                }
            }
        );
    }

    // =====================================================
    // LEADERBOARD NAVIGATION
    // =====================================================

    if (btnShowLeaderboard) {
        btnShowLeaderboard.addEventListener(
            "click",
            () => {
                ensureAudioContext();
                fetchLeaderboard();

                overlay.style.setProperty(
                    "display",
                    "none",
                    "important"
                );

                couponOverlay.style.setProperty(
                    "display",
                    "none",
                    "important"
                );

                leaderboardOverlay.style.setProperty(
                    "display",
                    "flex",
                    "important"
                );

                if (
                    document.pointerLockElement ===
                    gameZone
                ) {
                    document.exitPointerLock();
                } else if (gameActive) {
                    pauseGameLogic();
                }
            }
        );
    }

    if (btnCloseLeaderboard) {
        btnCloseLeaderboard.addEventListener(
            "click",
            () => {
                leaderboardOverlay.style.setProperty(
                    "display",
                    "none",
                    "important"
                );

                if (isGameOver) {
                    overlay.style.setProperty(
                        "display",
                        "flex",
                        "important"
                    );
                } else {
                    goofyTitle.innerText =
                        "SYSTEM IDLE";

                    goofyTitle.style.color =
                        "#fff";

                    goofyDesc.innerText =
                        "CLICK BUTTON TO RE-LOCK MOUSE & RESUME";

                    btnStart.innerText =
                        "RESUME PROTOCOL";

                    leaderboardInputUI.style.display =
                        "none";

                    if (score > 0) {
                        btnCashOut.style.display =
                            "block";
                    }

                    overlay.style.setProperty(
                        "display",
                        "flex",
                        "important"
                    );
                }
            }
        );
    }

    // =====================================================
    // COUPON NAVIGATION
    // =====================================================

    const btnCopyCoupon =
        document.getElementById(
            "btnCopyCoupon"
        );

    const btnResumeFromCoupon =
        document.getElementById(
            "btnResumeFromCoupon"
        );

    if (btnCopyCoupon) {
        btnCopyCoupon.addEventListener(
            "click",
            function () {
                const couponCodeElement =
                    document.getElementById(
                        "generatedCouponCode"
                    );

                if (!couponCodeElement) {
                    return;
                }

                const code =
                    couponCodeElement.innerText;

                if (
                    code &&
                    code.startsWith(
                        "VV-404"
                    )
                ) {
                    navigator.clipboard
                        .writeText(code)
                        .then(() => {
                            this.innerHTML =
                                '<i class="fa-solid fa-check fs-4"></i>';

                            setTimeout(() => {
                                this.innerHTML =
                                    '<i class="fa-solid fa-copy fs-4"></i>';
                            }, 2000);
                        })
                        .catch(() => {});
                }
            }
        );
    }

    if (btnResumeFromCoupon) {
        btnResumeFromCoupon.addEventListener(
            "click",
            () => {
                triggerLockOrTouchStart();
            }
        );
    }

    // =====================================================
    // PAGE CLEANUP
    // =====================================================

    document.addEventListener(
        "visibilitychange",
        () => {
            if (document.hidden) {
                resetVrScene();

                if (gameActive) {
                    pauseGameLogic();
                }
            }
        }
    );

    window.addEventListener(
        "pagehide",
        () => {
            clearInterval(
                spawnerInterval
            );

            cancelAnimationFrame(
                loopId
            );

            cancelAnimationFrame(
                parallaxFrame
            );

            itemsArray.forEach(
                (item) => {
                    item.element.remove();
                }
            );

            itemsArray = [];

            if (
                audioCtx &&
                typeof audioCtx.close ===
                    "function"
            ) {
                audioCtx
                    .close()
                    .catch(() => {});
            }
        },
        { once: true }
    );
});