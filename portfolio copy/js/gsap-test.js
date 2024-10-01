import { gsap } from "gsap";
document.addEventListener('DOMContentLoaded', (event) => {
  gsap.registerPlugin(ScrollTrigger);
  
  gsap.to('.box1', {
    duration: 3,
    rotation:360,
    scale:2
  })

});