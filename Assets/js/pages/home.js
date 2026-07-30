window.addEventListener("load", function () {
  if (document.querySelector(".hero-section")) {
    const container = document.getElementById("particles-container");
    if (container) {
      for (let i = 0; i < 20; i++) {
        let p = document.createElement("div");
        p.classList.add("particle");
        let size = Math.random() * 15 + 5;
        p.style.width = size + "px";
        p.style.height = size + "px";
        p.style.left = Math.random() * 100 + "vw";
        p.style.top = Math.random() * 100 + "vh";
        p.style.animationDelay = Math.random() * 5 + "s";
        p.style.animationDuration = Math.random() * 10 + 5 + "s";
        container.appendChild(p);
      }
    }

    if (typeof gsap !== "undefined" && typeof ScrollTrigger !== "undefined") {
      const preloader = document.getElementById("vvPreloader");
      const skipPreloader =
        document.documentElement.classList.contains("skip-pl");

      if (preloader && document.querySelector(".hero-glass-card")) {
        if (!skipPreloader) {
          if (typeof lenis !== "undefined") lenis.stop();
          document.body.style.overflow = "hidden";

          const plCounter = document.getElementById("pl-counter");
          const plLogoFill = document.getElementById("pl-logo-text");

          const bootTimeline = gsap.timeline({
            onComplete: () => {
              if (typeof lenis !== "undefined") lenis.start();
              document.body.style.overflow = "";
              preloader.style.display = "none";
            },
          });

          bootTimeline
            .to(".pl-telemetry", {
              opacity: 1,
              duration: 0.6,
              ease: "power2.out",
            })
            .to(
              { val: 0 },
              {
                val: 100,
                duration: 2.5,
                ease: "power3.inOut",
                onUpdate: function () {
                  let progress = Math.round(this.targets()[0].val);
                  if (plCounter)
                    plCounter.innerText = progress.toString().padStart(3, "0");
                  if (plLogoFill)
                    plLogoFill.style.setProperty(
                      "--fill-width",
                      progress + "%",
                    );
                },
              },
              "<",
            )
            .to(
              ".pl-content",
              { opacity: 0, scale: 0.95, duration: 0.6, ease: "power2.in" },
              "+=0.3",
            )
            .to(
              ".pl-curtain-top",
              { yPercent: -100, duration: 1.4, ease: "power4.inOut" },
              "split",
            )
            .to(
              ".pl-curtain-bottom",
              { yPercent: 100, duration: 1.4, ease: "power4.inOut" },
              "split",
            )
            .fromTo(
              ".hero-glass-card",
              { y: 80, opacity: 0, scale: 0.95 },
              { y: 0, opacity: 1, scale: 1, duration: 1.4, ease: "power4.out" },
              "split+=0.4",
            )
            .fromTo(
              ".gsap-hero-title",
              { yPercent: 120, skewY: 3 },
              { yPercent: 0, skewY: 0, duration: 1.2, ease: "power4.out" },
              "split+=0.6",
            )
            .fromTo(
              ".gsap-hero-sub",
              { yPercent: 100, opacity: 0 },
              {
                yPercent: 0,
                opacity: 1,
                duration: 1.2,
                stagger: 0.2,
                ease: "power3.out",
              },
              "split+=0.8",
            );
        } else {
          // Bypass Preloader entirely
          if (preloader) preloader.style.display = "none";

          const heroTimeline = gsap.timeline();
          heroTimeline
            .fromTo(
              ".hero-glass-card",
              { y: 60, opacity: 0, scale: 0.95 },
              {
                y: 0,
                opacity: 1,
                scale: 1,
                duration: 1.5,
                ease: "power4.out",
                delay: 0.2,
              },
            )
            .fromTo(
              ".gsap-hero-title",
              { yPercent: 120, skewY: 3 },
              { yPercent: 0, skewY: 0, duration: 1.2, ease: "power4.out" },
              "-=1",
            )
            .fromTo(
              ".gsap-hero-sub",
              { yPercent: 100, opacity: 0 },
              {
                yPercent: 0,
                opacity: 1,
                duration: 1.2,
                stagger: 0.2,
                ease: "power3.out",
              },
              "-=0.8",
            );
        }
      } else if (document.querySelector(".hero-glass-card")) {
        const heroTimeline = gsap.timeline();
        heroTimeline
          .fromTo(
            ".hero-glass-card",
            { y: 60, opacity: 0, scale: 0.95 },
            {
              y: 0,
              opacity: 1,
              scale: 1,
              duration: 1.5,
              ease: "power4.out",
              delay: 0.2,
            },
          )
          .fromTo(
            ".gsap-hero-title",
            { yPercent: 120, skewY: 3 },
            { yPercent: 0, skewY: 0, duration: 1.2, ease: "power4.out" },
            "-=1",
          )
          .fromTo(
            ".gsap-hero-sub",
            { yPercent: 100, opacity: 0 },
            {
              yPercent: 0,
              opacity: 1,
              duration: 1.2,
              stagger: 0.2,
              ease: "power3.out",
            },
            "-=0.8",
          );
      }

      gsap.utils.toArray(".gsap-reveal").forEach((el) => {
        gsap.fromTo(
          el,
          { yPercent: 100, opacity: 0 },
          {
            scrollTrigger: {
              trigger: el.parentElement,
              start: "top 85%",
              toggleActions: "play none none reverse",
            },
            yPercent: 0,
            opacity: 1,
            duration: 1.4,
            ease: "power4.out",
          },
        );
      });

      const track = document.querySelector(".gallery-track");
      if (track) {
        const moveX = -(track.scrollWidth - window.innerWidth);
        const tl = gsap.timeline({
          scrollTrigger: {
            trigger: ".horizontal-gallery-wrapper",
            start: "top top",
            end: () => "+=" + (track.scrollWidth - window.innerWidth),
            pin: true,
            scrub: 0.5,
            invalidateOnRefresh: true,
          },
        });
        tl.to(track, { x: moveX, ease: "none" });

        gsap.utils.toArray(".horizontal-entrance").forEach((el) => {
          gsap.fromTo(
            el,
            { y: 80, opacity: 0 },
            {
              y: 0,
              opacity: 1,
              duration: 1.2,
              ease: "power3.out",
              scrollTrigger: {
                trigger: el,
                containerAnimation: tl,
                start: "left 85%",
                toggleActions: "play none none reverse",
              },
            },
          );
        });
        gsap.utils.toArray(".snap-text").forEach((textBlock) => {
          ScrollTrigger.create({
            trigger: textBlock,
            containerAnimation: tl,
            start: "left 75%",
            end: "right 25%",
            toggleClass: "is-in-focus",
          });
        });
      }

      const fanCards = document.querySelectorAll(".fan-card");
      if (fanCards.length > 0) {
        let fanState = [0, 1, 2, 3, 4, 5];
        const positionClasses = [
          "pos-left-2",
          "pos-left-1",
          "pos-center",
          "pos-right-1",
          "pos-right-2",
          "pos-hidden",
        ];
        let isFannedOut = false;

        function applyFanState() {
          fanCards.forEach((card, index) => {
            positionClasses.forEach((cls) => card.classList.remove(cls));
            card.classList.add(positionClasses[fanState[index]]);
            if (fanState[index] === 2) updatePedestal(card);
          });
        }

        function updatePedestal(centerCard) {
          const title = document.getElementById("pedestal-title");
          const price = document.getElementById("pedestal-price");
          const btn = document.getElementById("pedestal-btn");
          gsap.to([title, price, btn], {
            opacity: 0,
            y: 10,
            duration: 0.2,
            onComplete: () => {
              title.innerText = centerCard.dataset.title;
              price.innerText = centerCard.dataset.price;
              btn.href = centerCard.dataset.link;
              gsap.to([title, price, btn], {
                opacity: 1,
                y: 0,
                duration: 0.4,
                stagger: 0.05,
              });
            },
          });
        }

        fanCards.forEach((card, index) => {
          card.addEventListener("click", () => {
            if (!isFannedOut) return;
            let currentPos = fanState[index];
            if (currentPos === 5) return;
            let diff = 2 - currentPos;
            if (diff !== 0) {
              fanState = fanState.map((p) => (p + diff + 6) % 6);
              applyFanState();
            }
          });
        });

        ScrollTrigger.create({
          trigger: ".fan-container",
          start: "top 75%",
          once: true,
          onEnter: () => {
            applyFanState();
            isFannedOut = true;
          },
        });
      }

      if (document.querySelector(".anthem-section")) {
        gsap.to(".gsap-parallax-anthem", {
          scrollTrigger: {
            trigger: ".anthem-section",
            start: "top bottom",
            end: "bottom top",
            scrub: 0.5,
          },
          y: 150,
          ease: "none",
        });
        gsap.utils.toArray(".gsap-reveal-anthem").forEach((el) => {
          gsap.fromTo(
            el,
            { yPercent: 120, opacity: 0 },
            {
              scrollTrigger: {
                trigger: ".anthem-section",
                start: "top 60%",
                toggleActions: "play none none reverse",
              },
              yPercent: 0,
              opacity: 1,
              duration: 1.4,
              stagger: 0.2,
              ease: "power4.out",
            },
          );
        });
      }
    }
  }
});
