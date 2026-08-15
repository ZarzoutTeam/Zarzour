<script>
    (() => {
        if (window.__zsNotificationSoundInitialized) {
            return;
        }

        window.__zsNotificationSoundInitialized = true;

        let audioContext = null;
        let lastUnreadCount = null;
        let checkTimer = null;

        const unreadCount = () => {
            const buttons = document.querySelectorAll(
                '.fi-topbar-database-notifications-btn, .fi-sidebar-database-notifications-btn',
            );

            return [...buttons].reduce((largestCount, button) => {
                const count = Number.parseInt(button.querySelector('.fi-badge')?.textContent.trim() ?? '0', 10);

                return Number.isNaN(count) ? largestCount : Math.max(largestCount, count);
            }, 0);
        };

        const armAudio = async () => {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;

            if (!AudioContextClass) {
                return false;
            }

            audioContext ??= new AudioContextClass();

            if (audioContext.state === 'suspended') {
                await audioContext.resume();
            }

            return true;
        };

        const playChime = async () => {
            try {
                if (!await armAudio()) {
                    return;
                }

                const now = audioContext.currentTime;
                const gain = audioContext.createGain();
                const firstTone = audioContext.createOscillator();
                const secondTone = audioContext.createOscillator();

                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.16, now + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.45);

                firstTone.frequency.setValueAtTime(740, now);
                secondTone.frequency.setValueAtTime(988, now + 0.12);
                firstTone.type = 'sine';
                secondTone.type = 'sine';

                firstTone.connect(gain);
                secondTone.connect(gain);
                gain.connect(audioContext.destination);

                firstTone.start(now);
                firstTone.stop(now + 0.22);
                secondTone.start(now + 0.12);
                secondTone.stop(now + 0.45);
            } catch (error) {
                // The visual unread state remains available when autoplay is blocked.
            }
        };

        const checkUnreadNotifications = () => {
            const currentUnreadCount = unreadCount();

            if (lastUnreadCount !== null && currentUnreadCount > lastUnreadCount) {
                playChime();
            }

            lastUnreadCount = currentUnreadCount;
        };

        const scheduleCheck = () => {
            window.clearTimeout(checkTimer);
            checkTimer = window.setTimeout(checkUnreadNotifications, 100);
        };

        document.addEventListener('pointerdown', () => armAudio().catch(() => {}), { once: true });
        document.addEventListener('keydown', () => armAudio().catch(() => {}), { once: true });

        lastUnreadCount = unreadCount();
        new MutationObserver(scheduleCheck).observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true,
        });
    })();
</script>
