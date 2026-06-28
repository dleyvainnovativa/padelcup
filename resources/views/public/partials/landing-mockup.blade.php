{{-- Stylized product mockup: a mini live bracket in a floating browser frame.
     Pure markup/CSS — no screenshot needed. --}}
<div class="lp-mock">
    <div class="lp-mock__bar">
        <span class="lp-mock__dot"></span><span class="lp-mock__dot"></span><span class="lp-mock__dot"></span>
        <span class="lp-mock__url">padelcup.mx/t/copa-verano</span>
    </div>
    <div class="lp-mock__body">
        <div class="lp-mock__head">
            <span class="lp-mock__title">Copa Verano · 5ta Varonil</span>
            <span class="lp-mock__live"><span class="lp-mock__pulse"></span> En vivo</span>
        </div>

        <div class="lp-bracket">
            {{-- Round 1 --}}
            <div class="lp-br-col">
                <div class="lp-br-match lp-br-match--won">
                    <span class="lp-br-pair">García / Soto <b>6-3 6-4</b></span>
                    <span class="lp-br-pair lp-br-pair--lose">Méndez / Ruiz</span>
                </div>
                <div class="lp-br-match">
                    <span class="lp-br-pair">Torres / Vega <b>7-5</b></span>
                    <span class="lp-br-pair">Díaz / Lara</span>
                </div>
                <div class="lp-br-match lp-br-match--won">
                    <span class="lp-br-pair">Cruz / Mora <b>6-2 6-1</b></span>
                    <span class="lp-br-pair lp-br-pair--lose">Reyes / Ibarra</span>
                </div>
                <div class="lp-br-match">
                    <span class="lp-br-pair">Luna / Peña</span>
                    <span class="lp-br-pair">Ortiz / Gil</span>
                </div>
            </div>
            {{-- Semis --}}
            <div class="lp-br-col lp-br-col--mid">
                <div class="lp-br-match lp-br-match--won">
                    <span class="lp-br-pair">García / Soto <b>6-4</b></span>
                    <span class="lp-br-pair lp-br-pair--lose">Torres / Vega</span>
                </div>
                <div class="lp-br-match">
                    <span class="lp-br-pair">Cruz / Mora</span>
                    <span class="lp-br-pair">Luna / Peña</span>
                </div>
            </div>
            {{-- Final --}}
            <div class="lp-br-col lp-br-col--final">
                <div class="lp-br-match lp-br-match--final">
                    <span class="lp-br-pair">García / Soto</span>
                    <span class="lp-br-pair">Cruz / Mora</span>
                    <span class="lp-br-final-tag"><i class="fa-solid fa-trophy"></i> Final</span>
                </div>
            </div>
        </div>
    </div>
</div>
