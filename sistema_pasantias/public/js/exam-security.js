class ExamSecurity {
    constructor(evaluacionId) {
        this.evaluacionId = evaluacionId;
        this.warnings = 0;
        this.maxWarnings = 2;
        this.startTime = new Date();
        this.init();
    }
    
    init() {
        // Detectar cambio de pestaña/ventana
        document.addEventListener('visibilitychange', () => {
            if(document.hidden) {
                this.recordViolation('visibility_change');
            }
        });
        
        // Detectar blur/focus
        window.addEventListener('blur', () => {
            this.recordViolation('window_blur');
        });
        
        // Detectar intento de cerrar
        window.addEventListener('beforeunload', (e) => {
            if(!this.examFinished) {
                e.preventDefault();
                e.returnValue = '¿Estás seguro de que quieres salir? El examen se calificará como reprobado.';
                this.recordViolation('attempt_close');
            }
        });
        
        // Detectar F5 o Ctrl+R
        window.addEventListener('keydown', (e) => {
            if((e.ctrlKey || e.metaKey) && (e.key === 'r' || e.key === 'R')) {
                e.preventDefault();
                this.recordViolation('page_refresh');
                alert('No puedes recargar la página durante el examen');
            }
            if(e.key === 'F5') {
                e.preventDefault();
                this.recordViolation('f5_refresh');
            }
        });
        
        // Detectar clic derecho (opcional)
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.recordViolation('right_click');
            return false;
        });
        
        // Enviar latidos cada 30 segundos
        this.heartbeatInterval = setInterval(() => {
            this.sendHeartbeat();
        }, 30000);
    }
    
    recordViolation(eventType) {
        this.warnings++;
        
        // Enviar registro al servidor
        fetch('/api/log-violation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                evaluacion_id: this.evaluacionId,
                evento: eventType,
                warnings: this.warnings
            })
        });
        
        // Mostrar advertencia
        if(this.warnings <= this.maxWarnings) {
            alert(`ADVERTENCIA ${this.warnings}/${this.maxWarnings}: ${this.getMessage(eventType)}`);
        } else {
            this.forceSubmit(false, 'violacion');
        }
    }
    
    getMessage(eventType) {
        const messages = {
            'visibility_change': 'No cambies de pestaña durante el examen',
            'window_blur': 'Mantén el foco en la ventana del examen',
            'attempt_close': 'No cierres la ventana',
            'page_refresh': 'No recargues la página',
            'right_click': 'Menú contextual deshabilitado'
        };
        return messages[eventType] || 'Comportamiento no permitido';
    }
    
    sendHeartbeat() {
        fetch('/api/exam-heartbeat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                evaluacion_id: this.evaluacionId,
                timestamp: new Date().toISOString()
            })
        }).catch(() => {
            // Si hay error de conexión, posiblemente el usuario está haciendo trampa
            this.recordViolation('connection_lost');
        });
    }
    
    forceSubmit(success = false, reason = '') {
        clearInterval(this.heartbeatInterval);
        
        // Enviar señal al servidor
        fetch('/api/force-submit-exam', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                evaluacion_id: this.evaluacionId,
                success: success,
                reason: reason,
                final_time: new Date().toISOString()
            })
        }).then(() => {
            if(!success) {
                alert('EXAMEN CANCELADO: Se detectaron múltiples violaciones de seguridad.');
            }
            window.location.href = `/exam-result/${this.evaluacionId}`;
        });
    }
    
    finishExam() {
        this.examFinished = true;
        clearInterval(this.heartbeatInterval);
    }
}

// Uso en la página de examen
// const examSecurity = new ExamSecurity(123);