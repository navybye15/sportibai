// Sportibai Interactive 3D Particle Cloud
// Inspired by brewedops.com WebGL point-cloud physics
(function () {
    'use strict';

    function init3DHero() {
        var container = document.getElementById('spotibai-3d-container');
        if (!container || typeof THREE === 'undefined') return;

        var width = container.clientWidth || 450;
        var height = container.clientHeight || 450;

        // 1. Scene, Camera, Renderer
        var scene = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(45, width / height, 1, 1000);
        camera.position.z = 240;

        var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setClearColor(0x000000, 0);
        container.innerHTML = '';
        container.appendChild(renderer.domElement);

        // Circular circular soft particle texture
        function createCircleTexture() {
            var canvas = document.createElement('canvas');
            canvas.width = 32;
            canvas.height = 32;
            var ctx = canvas.getContext('2d');
            var grad = ctx.createRadialGradient(16, 16, 0, 16, 16, 16);
            grad.addColorStop(0, 'rgba(255,255,255,1)');
            grad.addColorStop(0.3, 'rgba(255,255,255,0.85)');
            grad.addColorStop(0.7, 'rgba(255,255,255,0.25)');
            grad.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.arc(16, 16, 16, 0, Math.PI * 2);
            ctx.fill();
            var texture = new THREE.Texture(canvas);
            texture.needsUpdate = true;
            return texture;
        }

        var particleTexture = createCircleTexture();
        var particleGroup = new THREE.Group();
        scene.add(particleGroup);

        // Ambient sparks (brewedops style floating embers/particles)
        var sparkCount = 180;
        var sparkGeo = new THREE.BufferGeometry();
        var sparkPos = new Float32Array(sparkCount * 3);
        var sparkCol = new Float32Array(sparkCount * 3);
        var sparkVel = [];

        for (var s = 0; s < sparkCount; s++) {
            sparkPos[s * 3]     = (Math.random() - 0.5) * 160;
            sparkPos[s * 3 + 1] = (Math.random() - 0.5) * 160;
            sparkPos[s * 3 + 2] = (Math.random() - 0.5) * 120;
            
            // Neon green or warm orange sparks
            var isGreen = Math.random() > 0.35;
            if (isGreen) {
                sparkCol[s * 3]     = 0.32;
                sparkCol[s * 3 + 1] = 0.95;
                sparkCol[s * 3 + 2] = 0.28;
            } else {
                sparkCol[s * 3]     = 0.95;
                sparkCol[s * 3 + 1] = 0.25;
                sparkCol[s * 3 + 2] = 0.22;
            }
            sparkVel.push({
                x: (Math.random() - 0.5) * 0.2,
                y: 0.2 + Math.random() * 0.45,
                z: (Math.random() - 0.5) * 0.2
            });
        }
        sparkGeo.setAttribute('position', new THREE.BufferAttribute(sparkPos, 3));
        sparkGeo.setAttribute('color', new THREE.BufferAttribute(sparkCol, 3));

        var sparkMat = new THREE.PointsMaterial({
            size: 2.8,
            map: particleTexture,
            vertexColors: true,
            transparent: true,
            opacity: 0.85,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        });
        var sparkPoints = new THREE.Points(sparkGeo, sparkMat);
        scene.add(sparkPoints);

        // Load and sample the Spotibai Avatar Image
        var img = new Image();
        img.crossOrigin = 'Anonymous';
        img.src = 'images/spotibai-avatar.jpg';

        var mainPoints = null;
        var originalPositions = null;

        img.onload = function () {
            var sampleCanvas = document.createElement('canvas');
            var sampleSize = 190; // Density
            sampleCanvas.width = sampleSize;
            sampleCanvas.height = sampleSize;
            var sctx = sampleCanvas.getContext('2d');
            sctx.drawImage(img, 0, 0, sampleSize, sampleSize);
            var imgData = sctx.getImageData(0, 0, sampleSize, sampleSize).data;

            var positions = [];
            var colors = [];

            var step = 1;
            var scale = 0.68;
            var center = sampleSize / 2;

            for (var y = 0; y < sampleSize; y += step) {
                for (var x = 0; x < sampleSize; x += step) {
                    var idx = (y * sampleSize + x) * 4;
                    var r = imgData[idx];
                    var g = imgData[idx + 1];
                    var b = imgData[idx + 2];
                    var brightness = (r * 0.299 + g * 0.587 + b * 0.114);

                    // Skip dark background
                    if (brightness > 22) {
                        var nx = (x - center) / center;
                        var ny = (y - center) / center;
                        var dist = Math.sqrt(nx * nx + ny * ny);

                        // 3D Spherical Head Arc
                        var zDepth = 0;
                        if (dist < 1.0) {
                            zDepth = Math.cos(dist * 1.5) * 36;
                        }

                        // Depth boost for highlights (nose, lips, cap brim)
                        zDepth += (brightness / 255) * 14;

                        // Extra projection for cap visor (upper front)
                        if (ny < -0.2 && dist < 0.75) {
                            zDepth += Math.abs(ny) * 12;
                        }

                        var posX = (x - center) * scale;
                        var posY = -(y - center) * scale;
                        var posZ = zDepth;

                        // Jitter slightly for organic stippling
                        posX += (Math.random() - 0.5) * 0.5;
                        posY += (Math.random() - 0.5) * 0.5;
                        posZ += (Math.random() - 0.5) * 1.0;

                        positions.push(posX, posY, posZ);

                        // Color handling with neon enhancement
                        var cr = r / 255;
                        var cg = g / 255;
                        var cb = b / 255;

                        // Boost neon green signature highlights
                        if (g > 70 && g > r * 0.9) {
                            cg = Math.min(1.0, cg * 1.35);
                            cr *= 0.65;
                            cb *= 0.65;
                        } else if (r > 80 && r > g * 1.2) {
                            // Cap red highlight
                            cr = Math.min(1.0, cr * 1.3);
                        }

                        colors.push(cr, cg, cb);
                    }
                }
            }

            var geometry = new THREE.BufferGeometry();
            originalPositions = new Float32Array(positions);
            geometry.setAttribute('position', new THREE.BufferAttribute(new Float32Array(positions), 3));
            geometry.setAttribute('color', new THREE.BufferAttribute(new Float32Array(colors), 3));

            var material = new THREE.PointsMaterial({
                size: 2.2,
                map: particleTexture,
                vertexColors: true,
                transparent: true,
                opacity: 0.92,
                blending: THREE.AdditiveBlending,
                depthWrite: false
            });

            mainPoints = new THREE.Points(geometry, material);
            particleGroup.add(mainPoints);

            // Hide loading indicator if present
            var loader = document.getElementById('spotibai-3d-loader');
            if (loader) loader.style.display = 'none';
        };

        img.onerror = function () {
            // Graceful fallback if image is missing: generate a 3D sport particle orb
            var sphereGeo = new THREE.SphereGeometry(45, 48, 48);
            var sphereMat = new THREE.PointsMaterial({
                size: 2.2,
                map: particleTexture,
                color: 0x51af43,
                transparent: true,
                opacity: 0.9,
                blending: THREE.AdditiveBlending
            });
            mainPoints = new THREE.Points(sphereGeo, sphereMat);
            particleGroup.add(mainPoints);
            var loader = document.getElementById('spotibai-3d-loader');
            if (loader) loader.style.display = 'none';
        };

        // 2. Interactive Physics: Drag & Hold Rotation
        var isDragging = false;
        var previousMousePosition = { x: 0, y: 0 };
        var targetRotationX = 0;
        var targetRotationY = 0;
        var currentRotationX = 0;
        var currentRotationY = 0;
        var autoRotateSpeed = 0.003;
        var dragVelocityX = 0;
        var dragVelocityY = 0;

        function onPointerDown(e) {
            isDragging = true;
            container.style.cursor = 'grabbing';
            var clientX = e.clientX || (e.touches && e.touches[0].clientX) || 0;
            var clientY = e.clientY || (e.touches && e.touches[0].clientY) || 0;
            previousMousePosition = { x: clientX, y: clientY };
            dragVelocityX = 0;
            dragVelocityY = 0;
        }

        function onPointerMove(e) {
            var clientX = e.clientX || (e.touches && e.touches[0].clientX) || 0;
            var clientY = e.clientY || (e.touches && e.touches[0].clientY) || 0;

            if (isDragging) {
                var deltaX = clientX - previousMousePosition.x;
                var deltaY = clientY - previousMousePosition.y;

                dragVelocityX = deltaX * 0.0065;
                dragVelocityY = deltaY * 0.0065;

                targetRotationY += dragVelocityX;
                targetRotationX += dragVelocityY;

                // Restrict vertical rotation to keep head upright
                targetRotationX = Math.max(-0.65, Math.min(0.65, targetRotationX));

                previousMousePosition = { x: clientX, y: clientY };
            }
        }

        function onPointerUp() {
            isDragging = false;
            container.style.cursor = 'grab';
        }

        container.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', onPointerUp);
        window.addEventListener('pointercancel', onPointerUp);

        // Touch support
        container.addEventListener('touchstart', onPointerDown, { passive: true });
        window.addEventListener('touchmove', onPointerMove, { passive: true });
        window.addEventListener('touchend', onPointerUp, { passive: true });

        // 3. Render Animation Loop
        var clock = new THREE.Clock();

        function animate() {
            requestAnimationFrame(animate);

            var elapsedTime = clock.getElapsedTime();

            if (!isDragging) {
                // Smooth deceleration and gentle idle orbit
                targetRotationY += autoRotateSpeed;
                dragVelocityX *= 0.94;
                dragVelocityY *= 0.94;
                targetRotationY += dragVelocityX;
                targetRotationX += dragVelocityY;
            }

            // Damped interpolation for ultra-smooth buttery motion
            currentRotationY += (targetRotationY - currentRotationY) * 0.085;
            currentRotationX += (targetRotationX - currentRotationX) * 0.085;

            particleGroup.rotation.y = currentRotationY;
            particleGroup.rotation.x = currentRotationX;

            // Gentle breathing float
            particleGroup.position.y = Math.sin(elapsedTime * 1.8) * 3.5;

            // Animate floating sparks / embers
            var pPos = sparkGeo.attributes.position.array;
            for (var i = 0; i < sparkCount; i++) {
                pPos[i * 3 + 1] += sparkVel[i].y;
                pPos[i * 3]     += sparkVel[i].x;
                pPos[i * 3 + 2] += sparkVel[i].z;

                // Reset spark when it floats too high
                if (pPos[i * 3 + 1] > 95) {
                    pPos[i * 3 + 1] = -95;
                    pPos[i * 3]     = (Math.random() - 0.5) * 150;
                    pPos[i * 3 + 2] = (Math.random() - 0.5) * 110;
                }
            }
            sparkGeo.attributes.position.needsUpdate = true;
            sparkPoints.rotation.y = elapsedTime * 0.05;

            renderer.render(scene, camera);
        }

        animate();

        // 4. Responsive Resize
        function onResize() {
            if (!container) return;
            var newW = container.clientWidth;
            var newH = container.clientHeight;
            if (newW === 0 || newH === 0) return;
            camera.aspect = newW / newH;
            camera.updateProjectionMatrix();
            renderer.setSize(newW, newH);
        }

        window.addEventListener('resize', onResize);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init3DHero);
    } else {
        init3DHero();
    }
})();
