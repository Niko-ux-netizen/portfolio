// const pi_canvas = document.getElementById('pi');
// const section = document.querySelector('section'); 
// console.log(pi_canvas);

// const ctx_pi = pi_canvas.getContext('2d');

// // Set the canvas width and height to match the section size
// function resizeCanvas() {
//     pi_canvas.width = section.offsetWidth * window.devicePixelRatio;
//     pi_canvas.height = section.offsetHeight * window.devicePixelRatio;
//     pi_canvas.style.width = `${section.offsetWidth}px`;
//     pi_canvas.style.height = `${section.offsetHeight}px`;
// }

// resizeCanvas(); // Set canvas size initially

// class ParticlePi {
//     constructor(x, y, effectPi) {
//         this.originX = x;
//         this.originY = y;
//         this.effectPi = effectPi;
//         this.x = Math.floor(x);
//         this.y = Math.floor(y);
//         this.ctx_pi = this.effectPi.ctx_pi;
//         this.ctx_pi.fillStyle = 'white';
//         this.vx = 0;
//         this.vy = 0;
//         this.ease = 0.1; // Lowered the ease for smoother movement
//         this.friction = 0.95;
//         this.dx = 0;
//         this.dy = 0;
//         this.distance = 0;
//         this.force = 0;
//         this.angle = 0;
//         this.size = Math.floor(Math.random() * 5);
//         this.draw();
//     }
//     draw(){
//         this.ctx_pi.beginPath();
//         this.ctx_pi.fillRect(this.x, this.y, this.size, this.size);
//     }
//     update(){
//         this.dx = this.effectPi.mouse.x - this.x;
//         this.dy = this.effectPi.mouse.y - this.y;
//         this.distance = this.dx * this.dx + this.dy * this.dy;
//         this.force = -this.effectPi.mouse.radius / this.distance * 8;

//         if(this.distance < this.effectPi.mouse.radius){
//             this.angle = Math.atan2(this.dy, this.dx);
//             this.vx += this.force * Math.cos(this.angle);
//             this.vy += this.force * Math.sin(this.angle);
//         }

//         // Smooth movement back to origin
//         this.x += (this.vx *= this.friction) + (this.originX - this.x) * this.ease;
//         this.y += (this.vy *= this.friction) + (this.originY - this.y) * this.ease;
//         this.draw();
//     }
// }

// class EffectPi {
//     constructor(width, height, context) {
//         this.width = width;
//         this.height = height;
//         this.ctx_pi = context;
//         this.circleX = this.width / 2;
//         this.circleY = this.height / 2;
//         this.circleRadius = 800; 

//         this.particlesArray = [];
//         this.gap = 20;
//         this.mouse = {
//             radius: 3000,
//             x: 0,
//             y: 0
//         };

//         window.addEventListener('mousemove', e => {
//             const sectionRect = section.getBoundingClientRect();
//             this.mouse.x = (e.clientX - sectionRect.left) * window.devicePixelRatio;
//             this.mouse.y = (e.clientY - sectionRect.top) * window.devicePixelRatio;
//         });

//         window.addEventListener('resize', () => {
//             resizeCanvas(); // Call the resize function to adjust canvas size
//             this.width = pi_canvas.width;
//             this.height = pi_canvas.height;
//             this.particlesArray = [];
//             this.init();
//         });

//         this.init();
//     }

//     init(){
//         for(let x = 0; x < this.width; x += this.gap){
//             for(let y = 0; y < this.height; y += this.gap){
//                 this.particlesArray.push(new ParticlePi(x, y, this));
//             }
//         }
//     }

//     update(){
//         this.ctx_pi.clearRect(0, 0, this.width, this.height);
//         for(let i = 0; i < this.particlesArray.length; i++){
//             this.particlesArray[i].update();
//         }
//     }
// }

// let effectPi = new EffectPi(pi_canvas.width, pi_canvas.height, ctx_pi);

// function animate(){
//     effectPi.update();
//     requestAnimationFrame(animate);
// }
// animate();
