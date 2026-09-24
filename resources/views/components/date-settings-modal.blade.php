<div class="modal fade" id="urbanpos-date-settings-modal" tabindex="-1" role="dialog" aria-labelledby="urbanposDateModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 500px;">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title font-weight-bold text-dark h6 mb-0" id="urbanposDateModalLabel">
                    <i class="fas fa-calendar-alt text-primary mr-2"></i> Date Entry & Format Preferences
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="form-group mb-3">
                    <label class="font-weight-bold text-uppercase small text-muted mb-2">
                        <i class="fas fa-hand-pointer mr-1 text-secondary"></i> Date Entry Mode
                    </label>
                    <div class="border rounded p-3 bg-light">
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" id="date-mode-manual" name="date_pref_mode" class="custom-control-input" value="manual">
                            <label class="custom-control-label font-weight-bold text-primary" for="date-mode-manual" style="cursor: pointer;">
                                ✍️ Hath se likhna (Fast Keyboard Typing)
                            </label>
                            <div class="small text-muted ml-4 mt-1">
                                Direct number type karein (e.g. <code>10042026</code>). Input box par click karne par <strong>calendar popup nahi aayega</strong>.
                            </div>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="date-mode-calendar" name="date_pref_mode" class="custom-control-input" value="calendar">
                            <label class="custom-control-label font-weight-bold text-success" for="date-mode-calendar" style="cursor: pointer;">
                                📅 Calendar Picker Popup
                            </label>
                            <div class="small text-muted ml-4 mt-1">
                                Field par click ya focus karne par visual calendar pop-up khulega.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="font-weight-bold text-uppercase small text-muted mb-2">
                        <i class="fas fa-globe mr-1 text-secondary"></i> Display / Input Format
                    </label>
                    <div class="border rounded p-3">
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" id="date-fmt-dmy-dash" name="date_pref_fmt" class="custom-control-input" value="DD-MM-YYYY">
                            <label class="custom-control-label font-weight-bold" for="date-fmt-dmy-dash" style="cursor: pointer;">
                                <code>DD-MM-YYYY</code> <span class="badge badge-success ml-1">Indian ERP Standard</span>
                            </label>
                            <div class="small text-muted ml-4">Example: <code>10-04-2026</code> (10th April 2026)</div>
                        </div>
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" id="date-fmt-ymd-dash" name="date_pref_fmt" class="custom-control-input" value="YYYY-MM-DD">
                            <label class="custom-control-label font-weight-bold" for="date-fmt-ymd-dash" style="cursor: pointer;">
                                <code>YYYY-MM-DD</code> <span class="badge badge-secondary ml-1">ISO Standard</span>
                            </label>
                            <div class="small text-muted ml-4">Example: <code>2026-04-10</code></div>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="date-fmt-dmy-slash" name="date_pref_fmt" class="custom-control-input" value="DD/MM/YYYY">
                            <label class="custom-control-label font-weight-bold" for="date-fmt-dmy-slash" style="cursor: pointer;">
                                <code>DD/MM/YYYY</code>
                            </label>
                            <div class="small text-muted ml-4">Example: <code>10/04/2026</code></div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info py-2 px-3 small mb-0">
                    <i class="fas fa-lightbulb text-warning mr-1"></i>
                    <strong>Shortcuts:</strong> <code>10042026</code> type karne par <strong>10-04-2026</strong> banega. Aaj ki date ke liye <code>t</code> ya <code>today</code> likhein.
                </div>
            </div>
            <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm font-weight-bold px-3 btn-save-date-settings shadow-sm">
                    <i class="fas fa-check mr-1"></i> Save Preferences
                </button>
            </div>
        </div>
    </div>
</div>
