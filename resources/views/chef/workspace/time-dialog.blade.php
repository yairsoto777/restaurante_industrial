<dialog class="cw-time-dialog" id="cw-time-dialog" aria-labelledby="cw-time-title" aria-describedby="cw-time-instruction">
    <header><span class="cw-time-eyebrow">HORARIO DEL SERVICIO</span><h2 id="cw-time-title">Seleccionar hora</h2></header>
    <div class="cw-time-digital"><button type="button" data-cw-time-step="hour" aria-label="Elegir hora"><span data-cw-time-hour>08</span></button><span aria-hidden="true">:</span><button type="button" data-cw-time-step="minute" aria-label="Elegir minutos"><span data-cw-time-minute>00</span></button><button type="button" class="cw-time-meridiem" data-cw-time-step="period" aria-label="Elegir a. m. o p. m."><span data-cw-time-period>a. m.</span></button></div>
    <nav class="cw-time-steps" aria-label="Pasos para seleccionar la hora"><button type="button" data-cw-time-step="hour">1. Hora</button><button type="button" data-cw-time-step="minute">2. Minutos</button><button type="button" data-cw-time-step="period">3. a. m. / p. m.</button></nav>
    <p id="cw-time-instruction" class="cw-time-instruction" aria-live="polite">Selecciona la hora en el reloj.</p>
    <div class="cw-time-dial" data-cw-time-dial tabindex="0" role="slider" aria-label="Hora" aria-valuemin="1" aria-valuemax="12" aria-valuenow="8">
        <span class="cw-time-hand" data-cw-time-hand aria-hidden="true"><i data-cw-time-tip></i></span><span class="cw-time-center" aria-hidden="true"></span><div data-cw-time-numbers></div>
    </div>
    <fieldset class="cw-time-period-choice" data-cw-time-period-choice hidden><legend>Selecciona el periodo del día</legend><button type="button" data-cw-time-meridiem="am">a. m.<small>De medianoche a mediodía</small></button><button type="button" data-cw-time-meridiem="pm">p. m.<small>De mediodía a medianoche</small></button></fieldset>
    <footer class="cw-time-footer"><button type="button" class="cw-button secondary" data-cw-time-cancel>Cancelar</button><button type="button" class="cw-button" data-cw-time-accept>Aceptar</button></footer>
</dialog>
