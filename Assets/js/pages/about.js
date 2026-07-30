window.addEventListener('load', function() {
    if (document.querySelector(".about-page-wrapper")) {
        gsap.to(".marquee-left", { scrollTrigger: { trigger: ".abt-hero", start: "top top", end: "bottom top", scrub: 1, }, x: "-20vw", ease: "none", });
        gsap.to(".marquee-right", { scrollTrigger: { trigger: ".abt-hero", start: "top top", end: "bottom top", scrub: 1, }, x: "20vw", ease: "none", });
        gsap.fromTo( ".gsap-abt-hero", { yPercent: 120, opacity: 0 }, { yPercent: 0, opacity: 1, duration: 1.4, ease: "power4.out", delay: 0.2 }, );
      
        if (window.innerWidth > 991) {
          ScrollTrigger.create({ trigger: "#artifact-trigger", start: "top top", end: "bottom bottom", pin: "#artifact-pinned", pinSpacing: false, });
          gsap.to("#artifact-img", { scrollTrigger: { trigger: "#artifact-trigger", start: "top top", end: "bottom bottom", scrub: true, }, scale: 1.15, filter: "grayscale(0%) contrast(1.15) brightness(1.2)", ease: "none", });
        }
      
        const abtTrack = document.getElementById("abt-track");
        if (abtTrack) {
          const moveXAbt = -(abtTrack.scrollWidth - window.innerWidth);
          const tlAbt = gsap.timeline({ scrollTrigger: { trigger: ".abt-horizontal-wrapper", start: "top top", end: () => "+=" + (abtTrack.scrollWidth - window.innerWidth), pin: true, scrub: 0.5, invalidateOnRefresh: true, }, });
          tlAbt.to(abtTrack, { x: moveXAbt, ease: "none" });
        }
      
        gsap.fromTo( ".gsap-reveal-founder", { y: 80, opacity: 0, filter: "blur(10px)" }, { scrollTrigger: { trigger: ".abt-founder-section", start: "top 80%" }, y: 0, opacity: 1, filter: "blur(0px)", duration: 1.5, ease: "power3.out", }, );
      }
});