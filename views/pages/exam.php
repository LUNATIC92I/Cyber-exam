<div class="container" id="exam-app"
     data-attempt="<?= e($attempt['uuid']) ?>"
     data-api="<?= e(path('api/exam.php')) ?>"
     data-total="<?= (int) $attempt['total_questions'] ?>">
    <div class="exam-layout">
        <section class="exam-main" aria-live="polite">
            <div class="exam-loading" data-loading>
                <div class="loader" aria-hidden="true"></div>
                <p>Preparing your exam…</p>
            </div>
            <div data-exam hidden>
                <div class="q-counter">
                    <h2>Question <b data-q-number>1</b> / <span data-q-total><?= (int) $attempt['total_questions'] ?></span></h2>
                    <div class="row">
                        <span class="badge badge-blue" data-q-category></span>
                        <span class="badge" data-q-difficulty></span>
                    </div>
                </div>
                <div class="q-card">
                    <div class="q-stage" data-stage>
                        <p class="q-text" data-q-text></p>
                        <div class="options" role="radiogroup" aria-label="Answer options" data-options></div>
                    </div>
                    <div class="exam-nav">
                        <button class="btn btn-outline" type="button" data-prev><?= icon('arrow-left') ?> Previous</button>
                        <button class="btn btn-ghost flag-btn" type="button" data-flag aria-pressed="false"><?= icon('flag') ?> <span>Flag</span></button>
                        <span class="spacer"></span>
                        <span class="save-state" data-save-state><span class="dot"></span><span data-save-text>Saved</span></span>
                        <button class="btn btn-primary" type="button" data-next>Next <?= icon('arrow-right', 'icon icon-arrow') ?></button>
                    </div>
                </div>
            </div>
        </section>

        <aside class="exam-side" id="exam-side" data-side aria-label="Question navigator">
            <div class="card card-sm">
                <div class="exam-stat"><span>Answered</span><b><span data-answered>0</span> / <?= (int) $attempt['total_questions'] ?></b></div>
                <div class="exam-stat"><span>Flagged for review</span><b data-flagged>0</b></div>
                <div class="exam-stat"><span>Passing score</span><b><?= (int) $attempt['passing_score'] ?>%</b></div>
            </div>
            <div class="card card-sm">
                <div class="palette" data-palette></div>
                <div class="legend"><span><i class="a"></i>Answered</span><span><i></i>Not answered</span><span><i class="f"></i>Flagged</span></div>
            </div>
            <button class="btn btn-primary btn-block" type="button" data-finish><?= icon('check-circle') ?> Finish exam</button>
        </aside>
    </div>
</div>

<div class="modal" data-modal="finish" role="dialog" aria-modal="true" aria-labelledby="finish-title" hidden>
    <div class="modal-card">
        <h2 id="finish-title">Submit your exam?</h2>
        <p class="text-muted" data-finish-summary></p>
        <p class="text-muted">Once submitted, your answers are final and your result is calculated immediately.</p>
        <div class="modal-actions">
            <button class="btn btn-ghost" type="button" data-modal-close>Continue exam</button>
            <button class="btn btn-primary" type="button" data-confirm-finish><?= icon('check') ?> Submit exam</button>
        </div>
    </div>
</div>

<div class="modal" data-modal="timeup" role="alertdialog" aria-modal="true" aria-labelledby="timeup-title" hidden>
    <div class="modal-card text-center">
        <div class="status-icon pulse"><?= icon('clock') ?></div>
        <h2 id="timeup-title">Time is up</h2>
        <p class="text-muted">Your exam is being submitted automatically…</p>
    </div>
</div>
