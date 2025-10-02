/**
 * CUFT Test Data Storage Manager
 *
 * Manages test data persistence in localStorage with FIFO and TTL management.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

(function() {
    'use strict';

    /**
     * Test Data Storage Manager Class
     */
    class CUFTTestDataStorage {
        constructor() {
            this.storageKey = 'cuft_test_sessions';
            this.maxSessions = 50;
            this.ttlHours = 24;
        }

        /**
         * Save session data
         */
        saveSession(sessionData) {
            try {
                const sessions = this.getAllSessions();

                // Add timestamp if not present
                if (!sessionData.timestamp) {
                    sessionData.timestamp = Date.now();
                }

                // Add new session at the beginning
                sessions.unshift(sessionData);

                // Enforce FIFO limit
                if (sessions.length > this.maxSessions) {
                    sessions.splice(this.maxSessions);
                }

                // Save to localStorage
                localStorage.setItem(this.storageKey, JSON.stringify(sessions));
                return true;

            } catch (error) {
                if (error.name === 'QuotaExceededError') {
                    // Clear old sessions and try again
                    this.clearExpiredSessions();
                    try {
                        localStorage.setItem(this.storageKey, JSON.stringify([sessionData]));
                        return true;
                    } catch (e) {
                        console.error('CUFT Storage: Failed to save after cleanup', e);
                    }
                }
                console.error('CUFT Storage: Failed to save session', error);
                return false;
            }
        }

        /**
         * Get the latest session
         */
        getLatestSession() {
            const sessions = this.getAllSessions();
            return sessions.length > 0 ? sessions[0] : null;
        }

        /**
         * Get all sessions
         */
        getAllSessions() {
            try {
                const stored = localStorage.getItem(this.storageKey);
                if (!stored) return [];

                const sessions = JSON.parse(stored);

                // Clean up expired sessions
                const now = Date.now();
                const ttlMs = this.ttlHours * 60 * 60 * 1000;

                return sessions.filter(session => {
                    if (!session.timestamp) return true; // Keep sessions without timestamp
                    return (now - session.timestamp) < ttlMs;
                });

            } catch (error) {
                console.error('CUFT Storage: Failed to retrieve sessions', error);
                return [];
            }
        }

        /**
         * Clear all sessions
         */
        clearAll() {
            try {
                localStorage.removeItem(this.storageKey);
                return true;
            } catch (error) {
                console.error('CUFT Storage: Failed to clear sessions', error);
                return false;
            }
        }

        /**
         * Clear expired sessions
         */
        clearExpiredSessions() {
            const sessions = this.getAllSessions();
            const now = Date.now();
            const ttlMs = this.ttlHours * 60 * 60 * 1000;

            const activeSessions = sessions.filter(session => {
                if (!session.timestamp) return false;
                return (now - session.timestamp) < ttlMs;
            });

            try {
                localStorage.setItem(this.storageKey, JSON.stringify(activeSessions));
                return true;
            } catch (error) {
                console.error('CUFT Storage: Failed to clear expired sessions', error);
                return false;
            }
        }

        /**
         * Check if storage is available
         */
        isStorageAvailable() {
            try {
                const test = '__cuft_storage_test__';
                localStorage.setItem(test, test);
                localStorage.removeItem(test);
                return true;
            } catch (e) {
                return false;
            }
        }
    }

    // Export to global scope
    window.CUFTTestDataStorage = CUFTTestDataStorage;

})();