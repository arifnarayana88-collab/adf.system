<?php
// modules/payroll/export.php - EXPORT EMPLOYEE & SALARY DATA
define('APP_ACCESS', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

if (!isModuleEnabled('payroll')) {
    include '../../includes/header.php';
    echo '<div class="main-content"><div class="alert alert-warning">Modul Gaji (Payroll) belum diaktifkan.</div></div>';
    include '../../includes/footer.php';
    exit;
}

$db = Database::getInstance();
$pageTitle = 'Export Data Payroll';

// Get available periods for selection
$months = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 
           7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

$available_periods = $db->fetchAll("SELECT DISTINCT period_month, period_year FROM payroll_periods ORDER BY period_year DESC, period_month DESC LIMIT 12") ?: [];

// Get all employees
$employees = $db->fetchAll("SELECT id, employee_code, full_name, position, department, base_salary FROM payroll_employees WHERE is_active = 1 ORDER BY full_name ASC");

// Default values
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

include '../../includes/header.php';
?>

<style>
:root {
    --exp-gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --exp-gradient-2: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --exp-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    --exp-radius: 16px;
}

.exp-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 0;
}

.exp-header {
    background: var(--exp-gradient-1);
    color: #fff;
    padding: 2rem 2.5rem;
    border-radius: var(--exp-radius);
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.exp-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
    border-radius: 50%;
}

.exp-header h1 {
    font-size: 1.65rem;
    font-weight: 800;
    margin: 0;
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.exp-header p {
    color: rgba(255,255,255,0.85);
    margin: 0.5rem 0 0;
    font-size: 0.95rem;
    position: relative;
    z-index: 2;
}

/* Export Options */
.exp-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.exp-option-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--exp-radius);
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: var(--exp-shadow);
}

.exp-option-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
    border-color: #667eea;
}

.exp-option-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, rgba(102,126,234,0.15), rgba(118,75,162,0.15));
}

.exp-option-card h3 {
    font-size: 1.05rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.exp-option-card p {
    font-size: 0.85rem;
    color: var(--text-tertiary);
    margin: 0 0 1rem;
    line-height: 1.5;
}

.exp-option-btn {
    display: inline-block;
    padding: 0.75rem 1.25rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s;
    width: 100%;
    text-align: center;
}

.exp-option-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102,126,234,0.4);
}

/* Export Form */
.exp-form-section {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--exp-radius);
    padding: 1.5rem;
    box-shadow: var(--exp-shadow);
}

.exp-form-title {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.exp-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.exp-form-group {
    display: flex;
    flex-direction: column;
}

.exp-form-group label {
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.exp-form-group select,
.exp-form-group input[type="month"],
.exp-form-group input[type="text"] {
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 0.9rem;
    transition: all 0.2s;
}

.exp-form-group select:focus,
.exp-form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.exp-checkboxes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.exp-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.exp-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.exp-checkbox label {
    cursor: pointer;
    font-size: 0.9rem;
    margin: 0;
}

.exp-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.exp-btn {
    padding: 0.85rem 1.75rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.exp-btn-excel {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
}

.exp-btn-excel:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16,185,129,0.4);
}

.exp-btn-pdf {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff;
}

.exp-btn-pdf:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239,68,68,0.4);
}

.exp-btn-csv {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: #fff;
}

.exp-btn-csv:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59,130,246,0.4);
}

.exp-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.exp-feature-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
    color: var(--text-tertiary);
}

.exp-feature-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

@media (max-width: 768px) {
    .exp-header {
        padding: 1.5rem;
    }
    
    .exp-header h1 {
        font-size: 1.4rem;
    }
    
    .exp-options {
        grid-template-columns: 1fr;
    }
    
    .exp-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="main-content">
    <div class="exp-container">
        <!-- Header -->
        <div class="exp-header">
            <h1>📊 Export Data Payroll</h1>
            <p>Ekspor data karyawan dan gaji dalam berbagai format</p>
        </div>

        <!-- Quick Export Options -->
        <div class="exp-options">
            <!-- Employee Master Data -->
            <div class="exp-option-card">
                <div class="exp-option-icon">👥</div>
                <h3>Data Karyawan</h3>
                <p>Ekspor data master semua karyawan aktif dengan informasi lengkap</p>
                <form method="POST" action="export-handler.php">
                    <input type="hidden" name="export_type" value="employees">
                    <button type="submit" class="exp-option-btn">↓ Export</button>
                </form>
            </div>

            <!-- Salary Data for Period -->
            <div class="exp-option-card">
                <div class="exp-option-icon">💰</div>
                <h3>Data Gaji Periode</h3>
                <p>Ekspor data gaji untuk periode bulan yang dipilih</p>
                <button type="button" class="exp-option-btn" onclick="openSalaryExport()">↓ Export</button>
            </div>

            <!-- Complete Export -->
            <div class="exp-option-card">
                <div class="exp-option-icon">📦</div>
                <h3>Data Lengkap</h3>
                <p>Ekspor semua data (karyawan + gaji + absensi) dalam satu file</p>
                <button type="button" class="exp-option-btn" onclick="openCompleteExport()">↓ Export</button>
            </div>
        </div>

        <!-- Advanced Export Form -->
        <div class="exp-form-section">
            <div class="exp-form-title">⚙️ Pengaturan Export Custom</div>

            <form id="exportForm" method="POST" action="export-handler.php">
                <!-- Period Selection -->
                <div class="exp-form-grid">
                    <div class="exp-form-group">
                        <label for="period">Pilih Periode</label>
                        <select name="period" id="period" required>
                            <option value="">-- Pilih Bulan & Tahun --</option>
                            <?php foreach ($available_periods as $p): ?>
                                <option value="<?php echo $p['period_month'] . '-' . $p['period_year']; ?>" 
                                    <?php echo ($p['period_month'] == $selected_month && $p['period_year'] == $selected_year) ? 'selected' : ''; ?>>
                                    <?php echo $months[$p['period_month']] . ' ' . $p['period_year']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="exp-form-group">
                        <label for="department_filter">Filter Departemen (Optional)</label>
                        <select name="department_filter" id="department_filter">
                            <option value="">-- Semua Departemen --</option>
                            <?php 
                            $depts = $db->fetchAll("SELECT DISTINCT department FROM payroll_employees WHERE is_active = 1 AND department IS NOT NULL ORDER BY department");
                            foreach ($depts as $d):
                            ?>
                                <option value="<?php echo htmlspecialchars($d['department']); ?>">
                                    <?php echo htmlspecialchars($d['department']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Data Selection -->
                <div class="exp-form-group" style="margin-bottom: 1.5rem;">
                    <label style="text-transform: uppercase; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.75rem; display: block;">Pilih Data yang Akan Diekspor</label>
                    <div class="exp-checkboxes">
                        <div class="exp-checkbox">
                            <input type="checkbox" name="include_employee_data" id="inc_emp" value="1" checked>
                            <label for="inc_emp">Data Karyawan</label>
                        </div>
                        <div class="exp-checkbox">
                            <input type="checkbox" name="include_attendance" id="inc_att" value="1" checked>
                            <label for="inc_att">Absensi/Work Hours</label>
                        </div>
                        <div class="exp-checkbox">
                            <input type="checkbox" name="include_salary_details" id="inc_sal" value="1" checked>
                            <label for="inc_sal">Detail Gaji</label>
                        </div>
                        <div class="exp-checkbox">
                            <input type="checkbox" name="include_deductions" id="inc_ded" value="1" checked>
                            <label for="inc_ded">Potongan/Deduction</label>
                        </div>
                        <div class="exp-checkbox">
                            <input type="checkbox" name="include_bank_info" id="inc_bank" value="1" checked>
                            <label for="inc_bank">Info Bank</label>
                        </div>
                    </div>
                </div>

                <!-- Export Buttons -->
                <input type="hidden" name="export_type" value="custom">
                <div class="exp-buttons">
                    <button type="submit" name="format" value="excel" class="exp-btn exp-btn-excel">
                        📊 Export ke Excel
                    </button>
                    <button type="submit" name="format" value="csv" class="exp-btn exp-btn-csv">
                        📄 Export ke CSV
                    </button>
                    <button type="submit" name="format" value="pdf" class="exp-btn exp-btn-pdf">
                        📕 Export ke PDF
                    </button>
                </div>

                <!-- Features -->
                <div class="exp-features">
                    <div class="exp-feature-item">
                        <div class="exp-feature-icon">✓</div>
                        <span>Format Excel (.xlsx)</span>
                    </div>
                    <div class="exp-feature-item">
                        <div class="exp-feature-icon">✓</div>
                        <span>Format CSV</span>
                    </div>
                    <div class="exp-feature-item">
                        <div class="exp-feature-icon">✓</div>
                        <span>Format PDF</span>
                    </div>
                    <div class="exp-feature-item">
                        <div class="exp-feature-icon">✓</div>
                        <span>Filter by Departemen</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSalaryExport() {
    const period = prompt('Masukkan periode (contoh: 2-2025 untuk Feb 2025)');
    if (period) {
        const [m, y] = period.split('-');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'export-handler.php';
        
        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'export_type';
        typeInput.value = 'salary';
        
        const formatInput = document.createElement('input');
        formatInput.type = 'hidden';
        formatInput.name = 'format';
        formatInput.value = 'excel';
        
        const periodInput = document.createElement('input');
        periodInput.type = 'hidden';
        periodInput.name = 'period';
        periodInput.value = m.padStart(2, '0') + '-' + y;
        
        form.appendChild(typeInput);
        form.appendChild(formatInput);
        form.appendChild(periodInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function openCompleteExport() {
    const period = prompt('Masukkan periode (contoh: 2-2025 untuk Feb 2025)');
    if (period) {
        const [m, y] = period.split('-');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'export-handler.php';
        
        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'export_type';
        typeInput.value = 'complete';
        
        const formatInput = document.createElement('input');
        formatInput.type = 'hidden';
        formatInput.name = 'format';
        formatInput.value = 'excel';
        
        const periodInput = document.createElement('input');
        periodInput.type = 'hidden';
        periodInput.name = 'period';
        periodInput.value = m.padStart(2, '0') + '-' + y;
        
        form.appendChild(typeInput);
        form.appendChild(formatInput);
        form.appendChild(periodInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-select first available period if exists
document.addEventListener('DOMContentLoaded', function() {
    const periodSelect = document.getElementById('period');
    if (periodSelect && periodSelect.options.length > 1) {
        // First option is placeholder, select second if available
        if (periodSelect.options.length > 1) {
            periodSelect.selectedIndex = 1;
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
