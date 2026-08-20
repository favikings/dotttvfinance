<?php

declare(strict_types=1);

/**
 * Settings module — Super Admin only per PRD §3.2. Every action is guarded
 * with Permission::require($user, 'settings', <action>), and every
 * create/edit/delete runs inside a DB transaction that also writes an
 * audit_log row (CLAUDE.md rule 3): if the audit write fails, the change
 * rolls back with it.
 */
class SettingsController
{
    // ------------------------------------------------------------------
    // Shared plumbing
    // ------------------------------------------------------------------

    private function guard(string $action): array
    {
        $user = Auth::user();
        Permission::require($user, 'settings', $action);
        return $user;
    }

    private function verifyCsrf(): bool
    {
        return Csrf::verify($_POST['_csrf'] ?? null);
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash_' . $type] = $message;
    }

    private function redirectTo(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

    /** Back to the referer if it's a same-app path, otherwise the departments screen. */
    private function back(): void
    {
        $target = '/settings/departments';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (is_string($referer) && $referer !== '') {
            $refPath = parse_url($referer, PHP_URL_PATH) ?: '';
            $appPath = rtrim((string) (parse_url(url('/'), PHP_URL_PATH) ?: '/'), '/') . '/';
            if ($refPath !== '' && str_starts_with($refPath, $appPath)) {
                $target = $refPath;
            }
        }
        header('Location: ' . $target);
        exit;
    }

    private function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo '405 Method Not Allowed';
            exit;
        }
    }

    // ------------------------------------------------------------------
    // Index — settings has no standalone page, point at the first section
    // ------------------------------------------------------------------

    public function index(): void
    {
        $this->guard('view');
        $this->redirectTo('/settings/departments');
    }

    // ------------------------------------------------------------------
    // Departments CRUD
    // ------------------------------------------------------------------

    public function departments(): void
    {
        $this->guard('view');
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
        $edit = ($editId !== null && $editId > 0) ? Department::find($editId) : null;

        View::render('settings/departments', [
            'title' => 'Departments',
            'departments' => Department::all(),
            'editDepartment' => $edit,
        ]);
    }

    public function createDepartment(): void
    {
        $this->guard('create');
        $this->requirePost();
        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $this->flash('error', 'Department name is required.');
            $this->back();
        }
        if (Department::nameExists($name)) {
            $this->flash('error', "A department named \"{$name}\" already exists.");
            $this->back();
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $id = Department::create($name);
            AuditLogger::record($this->guard('create')['id'], 'create', 'departments', $id, [], ['name' => $name]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "Department \"{$name}\" created.");
        $this->back();
    }

    public function updateDepartment(): void
    {
        $this->guard('edit');
        $this->requirePost();
        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }

        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $current = Department::find($id);

        if ($current === null) {
            $this->flash('error', 'Department not found.');
            $this->back();
        }
        if ($name === '') {
            $this->flash('error', 'Department name is required.');
            $this->back();
        }
        if (Department::nameExists($name, $id)) {
            $this->flash('error', "A department named \"{$name}\" already exists.");
            $this->back();
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Department::update($id, $name);
            AuditLogger::record($this->guard('edit')['id'], 'edit', 'departments', $id, ['name' => $current['name']], ['name' => $name]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "Department renamed to \"{$name}\".");
        $this->back();
    }

    public function deleteDepartment(): void
    {
        $this->guard('delete');
        $this->requirePost();
        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }

        $id = (int) ($_POST['id'] ?? 0);
        $current = Department::find($id);

        if ($current === null) {
            $this->flash('error', 'Department not found.');
            $this->back();
        }
        if (Department::inUse($id)) {
            $this->flash('error', 'Cannot delete: this department is used by expenses, invoices, users, or payroll records.');
            $this->back();
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Department::delete($id);
            AuditLogger::record($this->guard('delete')['id'], 'delete', 'departments', $id, ['name' => $current['name']], []);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "Department \"{$current['name']}\" deleted.");
        $this->back();
    }

    // ------------------------------------------------------------------
    // Expense categories CRUD
    // ------------------------------------------------------------------

    public function categories(): void
    {
        $this->guard('view');
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
        $edit = ($editId !== null && $editId > 0) ? ExpenseCategory::find($editId) : null;

        View::render('settings/categories', [
            'title' => 'Expense Categories',
            'categories' => ExpenseCategory::all(),
            'editCategory' => $edit,
        ]);
    }

    public function createCategory(): void
    {
        $this->guard('create');
        $this->requirePost();
        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $this->flash('error', 'Category name is required.');
            $this->back();
        }
        if (ExpenseCategory::nameExists($name)) {
            $this->flash('error', "A category named \"{$name}\" already exists.");
            $this->back();
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $id = ExpenseCategory::create($name);
            AuditLogger::record($this->guard('create')['id'], 'create', 'expense_categories', $id, [], ['name' => $name]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "Category \"{$name}\" created.");
        $this->back();
    }

    public function updateCategory(): void
    {
        $this->guard('edit');
        $this->requirePost();
        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }

        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $current = ExpenseCategory::find($id);

        if ($current === null) {
            $this->flash('error', 'Category not found.');
            $this->back();
        }
        if ($name === '') {
            $this->flash('error', 'Category name is required.');
            $this->back();
        }
        if (ExpenseCategory::nameExists($name, $id)) {
            $this->flash('error', "A category named \"{$name}\" already exists.");
            $this->back();
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            ExpenseCategory::update($id, $name);
            AuditLogger::record($this->guard('edit')['id'], 'edit', 'expense_categories', $id, ['name' => $current['name']], ['name' => $name]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "Category renamed to \"{$name}\".");
        $this->back();
    }

    public function deleteCategory(): void
    {
        $this->guard('delete');
        $this->requirePost();
        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }

        $id = (int) ($_POST['id'] ?? 0);
        $current = ExpenseCategory::find($id);

        if ($current === null) {
            $this->flash('error', 'Category not found.');
            $this->back();
        }
        if (ExpenseCategory::inUse($id)) {
            $this->flash('error', 'Cannot delete: this category is used by existing expenses.');
            $this->back();
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            ExpenseCategory::delete($id);
            AuditLogger::record($this->guard('delete')['id'], 'delete', 'expense_categories', $id, ['name' => $current['name']], []);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "Category \"{$current['name']}\" deleted.");
        $this->back();
    }

    // ------------------------------------------------------------------
    // Fund account — read-only in v1 (single float, PRD §10.2)
    // ------------------------------------------------------------------

    public function fundAccounts(): void
    {
        $this->guard('view');

        View::render('settings/fund_accounts', [
            'title' => 'Fund Account',
            'fundAccounts' => FundAccount::allWithBalances(),
        ]);
    }

    // ------------------------------------------------------------------
    // Approval rules editor
    // ------------------------------------------------------------------

    public function approvalRules(): void
    {
        $this->guard('view');

        View::render('settings/approval_rules', [
            'title' => 'Approval Rules',
            'rules' => ApprovalRule::all(),
        ]);
    }

    /**
     * Batch save of every tier row (existing edits, a possible new row, and
     * any marked-for-delete rows) as one form. The whole tier set is
     * validated together against PRD §3.3's sanity rules before anything
     * is written, so a save can never leave the rules broken.
     */
    public function saveApprovalRules(): void
    {
        $this->guard('edit');
        $this->requirePost();
        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }

        $ids = $_POST['tier_id'] ?? [];
        if (!is_array($ids) || $ids === []) {
            $this->flash('error', 'No approval rules to save.');
            $this->back();
        }

        $surviving = [];
        $deletions = [];

        foreach ($ids as $i => $rawId) {
            $rowId = trim((string) $rawId);
            $isDelete = !empty($_POST['delete'][$i]);
            $pos = $i + 1;

            if ($isDelete) {
                if ($rowId !== 'new' && $rowId !== '') {
                    $deletions[] = (int) $rowId;
                }
                continue;
            }

            $minRaw = trim((string) ($_POST['min_amount'][$i] ?? ''));
            if (!is_numeric($minRaw) || (float) $minRaw < 0) {
                $this->flash('error', "Tier {$pos} minimum amount must be a non-negative number.");
                $this->back();
            }
            $minCents = (int) round((float) $minRaw * 100);

            $maxRaw = trim((string) ($_POST['max_amount'][$i] ?? ''));
            $maxCents = null;
            if ($maxRaw !== '') {
                if (!is_numeric($maxRaw) || (float) $maxRaw <= 0) {
                    $this->flash('error', "Tier {$pos} maximum amount must be a positive number.");
                    $this->back();
                }
                $maxCents = (int) round((float) $maxRaw * 100);
                if ($maxCents <= $minCents) {
                    $this->flash('error', "Tier {$pos} maximum must be greater than its minimum.");
                    $this->back();
                }
            }

            // Checkbox order in the DOM (accountant -> gm -> chairman) is the
            // order PHP receives them in, so the role sequence is preserved.
            $roles = [];
            $posted = is_array($_POST['required_roles'][$i] ?? null) ? (array) $_POST['required_roles'][$i] : [];
            foreach (['accountant', 'gm', 'chairman'] as $role) {
                if (in_array($role, $posted, true)) {
                    $roles[] = $role;
                }
            }

            if ($roles === []) {
                $this->flash('error', "Tier {$pos} must have at least one required approver.");
                $this->back();
            }

            $surviving[] = [
                'id' => $rowId,
                'min_cents' => $minCents,
                'max_cents' => $maxCents,
                'roles' => $roles,
            ];
        }

        if ($surviving === []) {
            $this->flash('error', 'At least one approval tier is required.');
            $this->back();
        }

        $validationError = $this->validateTiers($surviving);
        if ($validationError !== null) {
            $this->flash('error', $validationError);
            $this->back();
        }

        // Snapshot of the current DB state before any writes, for audit
        // before/after payloads.
        $existingMap = [];
        foreach (ApprovalRule::all() as $rule) {
            $existingMap[(int) $rule['id']] = $rule;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $user = $this->guard('edit');

            foreach ($deletions as $delId) {
                if (!isset($existingMap[$delId])) {
                    continue;
                }
                $before = $this->rulePayload($existingMap[$delId]);
                ApprovalRule::delete($delId);
                AuditLogger::record($user['id'], 'delete', 'approval_rules', $delId, $before, []);
            }

            // Move surviving existing tiers out of the way so renumbering to
            // contiguous 1..N can never collide on the unique tier_order key.
            $survivorIds = [];
            foreach ($surviving as $tier) {
                if ($tier['id'] !== 'new' && $tier['id'] !== '') {
                    $survivorIds[] = (int) $tier['id'];
                }
            }
            if ($survivorIds !== []) {
                $in = implode(',', array_fill(0, count($survivorIds), '?'));
                $stmt = $pdo->prepare("UPDATE approval_rules SET tier_order = tier_order + 10000 WHERE id IN ({$in})");
                $stmt->execute($survivorIds);
            }

            $tierNo = 1;
            foreach ($surviving as $tier) {
                $minStr = $this->centsToNaira($tier['min_cents']);
                $maxStr = $tier['max_cents'] === null ? null : $this->centsToNaira($tier['max_cents']);

                if ($tier['id'] === 'new' || $tier['id'] === '') {
                    $newId = ApprovalRule::insert($minStr, $maxStr, $tier['roles'], $tierNo);
                    AuditLogger::record($user['id'], 'create', 'approval_rules', $newId, [], $this->rulePayload([
                        'tier_order' => $tierNo,
                        'min_amount' => $minStr,
                        'max_amount' => $maxStr,
                        'required_roles' => $tier['roles'],
                    ]));
                } else {
                    $id = (int) $tier['id'];
                    if (isset($existingMap[$id])) {
                        $before = $this->rulePayload($existingMap[$id]);
                        $after = $this->rulePayload([
                            'tier_order' => $tierNo,
                            'min_amount' => $minStr,
                            'max_amount' => $maxStr,
                            'required_roles' => $tier['roles'],
                        ]);
                        ApprovalRule::update($id, $minStr, $maxStr, $tier['roles'], $tierNo);
                        if ($before !== $after) {
                            AuditLogger::record($user['id'], 'edit', 'approval_rules', $id, $before, $after);
                        }
                    }
                }
                $tierNo++;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', 'Approval rules updated.');
        $this->back();
    }

    /**
     * PRD §3.3 sanity checks across the full, form-ordered tier set.
     *
     * @param array<int, array{min_cents: int, max_cents: ?int, roles: list<string>}> $tiers
     */
    private function validateTiers(array $tiers): ?string
    {
        $prevMax = null;
        $count = count($tiers);

        foreach ($tiers as $i => $tier) {
            $pos = $i + 1;

            if ($i === 0) {
                if ($tier['min_cents'] !== 0) {
                    return 'Tier 1 must start at ₦0.00.';
                }
            } elseif ($prevMax === null) {
                return 'Only the highest tier may be open-ended (no maximum).';
            } elseif ($tier['min_cents'] !== $prevMax + 1) {
                return "Tier {$pos} must start exactly where Tier {$i} ends (₦" . $this->centsToNaira($prevMax) . ') — no gaps or overlaps between tiers.';
            }

            if ($tier['max_cents'] === null && $i < $count - 1) {
                return 'Only the highest tier may be open-ended (no maximum).';
            }

            if ($i > 0 && !$this->isOrderedSuperset($tiers[$i - 1]['roles'], $tier['roles'])) {
                return "Tier {$pos} must include every approver from Tier {$i}, in the same order.";
            }

            $prevMax = $tier['max_cents'];
        }

        return null;
    }

    /** @param list<string> $subset @param list<string> $superset */
    private function isOrderedSuperset(array $subset, array $superset): bool
    {
        $j = 0;
        $len = count($subset);
        foreach ($superset as $role) {
            if ($j < $len && $role === $subset[$j]) {
                $j++;
            }
        }
        return $j === $len;
    }

    private function centsToNaira(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function rulePayload(array $rule): array
    {
        return [
            'tier_order' => (int) $rule['tier_order'],
            'min_amount' => (string) $rule['min_amount'],
            'max_amount' => $rule['max_amount'] === null ? null : (string) $rule['max_amount'],
            'required_roles' => array_values((array) $rule['required_roles']),
        ];
    }

    // ------------------------------------------------------------------
    // User management CRUD + role assignment
    // ------------------------------------------------------------------

    public function users(): void
    {
        $this->guard('view');
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
        $edit = ($editId !== null && $editId > 0) ? User::find($editId) : null;

        View::render('settings/users', [
            'title' => 'Users',
            'users' => User::all(),
            'roles' => Role::all(),
            'departments' => Department::all(),
            'editUser' => $edit,
        ]);
    }

    public function createUser(): void
    {
        $this->guard('create');
        $this->requirePost();
        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $deptRaw = $_POST['department_id'] ?? '';
        $departmentId = ($deptRaw !== '' && $deptRaw !== null) ? (int) $deptRaw : null;
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '') {
            $this->flash('error', 'Name is required.');
            $this->back();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'A valid email address is required.');
            $this->back();
        }
        if (User::emailExists($email)) {
            $this->flash('error', "A user with email \"{$email}\" already exists.");
            $this->back();
        }
        if (!Role::exists($roleId)) {
            $this->flash('error', 'Please select a role.');
            $this->back();
        }
        if (strlen($password) < 8) {
            $this->flash('error', 'Password must be at least 8 characters.');
            $this->back();
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $id = User::create([
                'name' => $name,
                'email' => $email,
                'role_id' => $roleId,
                'department_id' => $departmentId,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'status' => 'active',
            ]);
            AuditLogger::record($this->guard('create')['id'], 'create', 'users', $id, [], $this->userPayload(User::find($id)));
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "User \"{$name}\" created.");
        $this->back();
    }

    public function updateUser(): void
    {
        $this->guard('edit');
        $this->requirePost();
        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }

        $id = (int) ($_POST['id'] ?? 0);
        $current = User::find($id);
        if ($current === null) {
            $this->flash('error', 'User not found.');
            $this->back();
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $deptRaw = $_POST['department_id'] ?? '';
        $departmentId = ($deptRaw !== '' && $deptRaw !== null) ? (int) $deptRaw : null;
        $status = $_POST['status'] ?? '';
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '') {
            $this->flash('error', 'Name is required.');
            $this->back();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'A valid email address is required.');
            $this->back();
        }
        if (User::emailExists($email, $id)) {
            $this->flash('error', "A user with email \"{$email}\" already exists.");
            $this->back();
        }
        if (!Role::exists($roleId)) {
            $this->flash('error', 'Please select a role.');
            $this->back();
        }
        if ($status !== 'active' && $status !== 'inactive') {
            $this->flash('error', 'Please select a status.');
            $this->back();
        }
        if ($password !== '' && strlen($password) < 8) {
            $this->flash('error', 'Password must be at least 8 characters.');
            $this->back();
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            User::update($id, [
                'name' => $name,
                'email' => $email,
                'role_id' => $roleId,
                'department_id' => $departmentId,
                'status' => $status,
                'password_hash' => $password !== '' ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]) : '',
            ]);
            $before = $this->userPayload($current);
            $after = $this->userPayload(User::find($id));
            AuditLogger::record($this->guard('edit')['id'], 'edit', 'users', $id, $before, $after);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "User \"{$name}\" updated.");
        $this->back();
    }

    public function deleteUser(): void
    {
        $this->guard('delete');
        $this->requirePost();
        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Your session expired. Please try again.');
            $this->back();
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) Auth::id()) {
            $this->flash('error', 'You cannot delete your own account.');
            $this->back();
        }

        $current = User::find($id);
        if ($current === null) {
            $this->flash('error', 'User not found.');
            $this->back();
        }
        if (User::hasRelatedRecords($id)) {
            $this->flash('error', 'Cannot delete: this user has financial or audit records. Deactivate them instead.');
            $this->back();
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            User::delete($id);
            AuditLogger::record($this->guard('delete')['id'], 'delete', 'users', $id, $this->userPayload($current), []);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->flash('success', "User \"{$current['name']}\" deleted.");
        $this->back();
    }

    /**
     * Audit-safe representation of a user row: identity + assignment + status,
     * deliberately excluding password_hash so hashes never land in the
     * append-only, long-retained audit table.
     */
    private function userPayload(?array $user): array
    {
        if ($user === null) {
            return [];
        }
        return [
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role_name'] ?? null,
            'department' => $user['department_name'] ?? null,
            'status' => $user['status'],
        ];
    }
}
