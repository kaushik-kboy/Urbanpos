# MASTER PROMPT — URBANPOS STOCK & EXPIRY VERIFICATION V1

## 1. Role and Objective

Act as a Senior Laravel Architect, Flutter Developer, Database Architect, UI/UX Designer, API Security Engineer, and QA Engineer.

I have an existing UrbanPOS application. Your task is to inspect the existing project and implement a new **Urban Pets Stock & Expiry Verification V1 module** without breaking existing functionality.

Work autonomously through the implementation phases wherever safe. I may leave you working overnight and will manually test the application the following morning.

Do not merely generate plans or UI mockups. Inspect the actual code, implement real functionality, integrate the APIs, run available tests, fix issues, and document the final state.

Do not claim something is implemented or tested unless the corresponding work was actually completed.

## 2. Existing Project and Architecture

The existing UrbanPOS application uses Laravel/PHP and MySQL or MariaDB. Inspect the actual installed versions and architecture before making changes.

Existing functionality may include:

- POS and sales
- Purchases and receiving
- Products and barcodes
- Multi-branch inventory
- Batch-wise stock and expiry tracking
- Physical stock reconciliation and approval
- Stock transfers
- Damaged and expired stock adjustments
- Purchase returns and sales returns
- Stock ledger and inventory history

First inspect the real code, database schema, authentication, roles, routes, models, services, and tests.

Previously identified components may include `ItemStock`, `BatchStockService`, and `StockLedgerService`. Verify their existence and actual behavior before using them.

Do not recreate existing functionality unnecessarily or assume undocumented model fields and method signatures.

### Required architecture

**Existing Laravel application**
- Keep Laravel as the backend and source of truth.
- Integrate the new module using existing project conventions.
- Use focused new controllers, services, policies, requests, routes, models, and migrations where appropriate.
- Reuse existing stock and ledger mechanisms.

**Separate Flutter application**
- Create the mobile project at `mobile-app/`.
- Keep Flutter code separate from the existing Laravel application code.
- Build a single Flutter codebase designed to support Android and iOS.
- Connect to Laravel through authenticated APIs.
- Never connect Flutter directly to MySQL/MariaDB.
- Never place database credentials or privileged secrets in the mobile application.

**Single inventory source of truth**
- Do not create a separate authoritative inventory database or duplicate stock balance.
- Use the existing UrbanPOS product, branch, batch, inventory, and ledger records.

## 3. Platform and Delivery Scope

Implement the shared Flutter application for both Android and iOS wherever practical.

### Current delivery

- Android application implementation and testing.
- Android debug/test build where the environment permits.
- Premium mobile UI and functional Laravel API integration.
- Cross-platform-compatible shared Flutter architecture.

### Future iOS release

- Implement shared features in a way that supports iOS.
- Check package compatibility and iOS-specific configuration.
- Run iOS builds and device/simulator tests only when the required macOS/Xcode environment is available.
- Document remaining signing, provisioning, device testing, and App Store requirements.

**The client receives Android only in the current phase. Do not publish or deliver either application to an app store without explicit authorization.**

Do not duplicate shared business logic just to create separate platform implementations. Keep platform-specific code isolated where necessary.

## 4. Database, Git and Local Environment Safety

I am working locally and intend to test the application the following morning.

- Preserve all existing project files and uncommitted changes.
- Inspect Git status, but do not commit, push, reset, switch branches, or overwrite user changes.
- Do not drop, truncate, reset, or recreate the existing database.
- Do not run destructive migrations.
- Do not run automated tests against production or casually modify my development database.
- Use a dedicated test database or safe test isolation where available.
- Verify database configuration before executing migrations.
- Keep migrations reversible where feasible.
- Never deploy or publish anything.
- Do not expose credentials, passwords, tokens, or environment secrets in reports.

The developer is taking a project and database backup. Check what can be verified, document any remaining risk, and do not assume a backup is valid merely because a command was attempted.

If a database operation is unsafe, stop that operation, document the blocker, and continue with independent safe work.

## 5. Core Business Workflow

The module must support:

1. Staff stock counting.
2. Expiry verification.
3. Barcode scanning and product search.
4. Branch, section, rack, and task context where supported.
5. Staff submissions and history.
6. Manager review and approval.
7. Recount and rejection workflows.
8. Controlled inventory corrections after approval.
9. Unknown barcode mapping requests and authorized review.
10. Dashboard, history, and audit information.

Inspect the existing project and client requirements before deciding the final data model.

Do not silently invent business rules that are not established.

## 6. Critical Inventory Rules

These rules are mandatory.

### Blind counting

- Staff must enter physical quantities without seeing system quantities during counting.
- Do not expose system quantity in the staff API response and merely hide it in the UI.
- Staff submissions create observations awaiting review.
- Staff submissions must not change inventory quantities or create stock adjustment ledger entries.

### Manager approval

- Only authorized managers may approve eligible observations.
- Rejected observations and recount requests must not modify inventory.
- Validate the product, branch, batch, permissions, observation state, and current inventory before approval.
- Apply approved corrections using the existing UrbanPOS stock services and ledger conventions.
- Do not bypass established inventory logic with arbitrary direct quantity updates.

### Concurrency and idempotency

Example:

- Original system quantity: 20
- Staff physical count: 17
- A sale occurs before approval, leaving current stock at 18
- Manager approves a target quantity of 17

If the approved business rule is to set the verified target quantity to 17, calculate the correction from current stock of 18, not stale stock of 20.

Use appropriate database transactions, row locking, current-stock revalidation, duplicate approval protection, and rollback behavior.

A failed approval must not leave partial inventory or ledger changes.

### Audit trail

Preserve relevant information, including:

- Product, branch, and batch
- Submitted physical quantity
- Original observation and current inventory context
- Approved target and actual adjustment
- Reason and remarks
- Submitted by, reviewed by, and timestamps
- Approval, recount, and rejection history

Reuse existing audit infrastructure where appropriate.

## 7. Stock Count Features

Implement:

- Blind stock counting
- Camera barcode scanning
- Manual barcode entry
- Product search
- Physical quantity entry
- Input validation
- Remarks where appropriate
- Save and Next Scan
- Successful scan feedback or beep where supported
- Task progress
- Submission confirmation
- My submissions/history
- Pending-review status
- Positive and negative variance indicators on authorized manager screens

Use real product identifiers, not product names as unique identifiers.

Handle unknown barcodes, repeated scans, invalid inputs, and API failures clearly.

Prevent duplicate submissions on repeated taps or network retries where necessary.

## 8. Expiry Verification

Support batch-aware expiry observations where the existing data model permits:

- Product and branch
- Batch identifier
- Expiry date
- Physical quantity where applicable
- Observation date/time
- Optional photo
- Optional remarks
- Submission and review status

Staff observations must not silently overwrite official batch expiry data or stock balances.

Expiry dashboard buckets must be configurable or based on confirmed existing requirements. Previously discussed ranges include 0–30, 31–60, and 61–120 days, while other notes mention a 60–90-day range.

Inspect available requirements and configuration. Do not silently choose between conflicting rules. Document any unresolved decision and avoid presenting an unconfirmed classification as final.

Handle expired items, missing dates, invalid dates, and batch mismatches explicitly.

## 9. Task Assignment and Manager Review

Where supported by the confirmed requirements, allow authorized managers to assign tasks using:

- Branch
- Section or rack
- Category or brand
- Selected products or batches
- Assigned staff
- Due date or priority, if required

Implement a manager review queue displaying relevant product, batch, system quantity, physical quantity, variance, remarks, evidence, submitter, and history.

Available review actions:

- Approve
- Request Recount
- Reject

Enforce valid status transitions on the server. Preserve prior observations when a recount is requested instead of silently overwriting the audit history.

Enforce branch access and role permissions on every relevant API.

### Barcode mapping

- Unknown barcodes must not automatically modify master product data.
- Mapping requests require authorized review.
- Inspect and reuse existing multiple-barcode/EAN support where possible.
- Prevent accidental or unauthorized mapping to the wrong product.

## 10. Premium Flutter UI/UX

Use the supplied premium UI reference screenshots as the visual direction.

Do not substitute a generic CRUD template or put screenshots in place of actual UI components.

Design a polished, professional inventory operations app with:

- Clean white/light-neutral surfaces
- Professional blue primary actions
- Readable dark typography
- Strong hierarchy and consistent spacing
- Well-designed cards and input fields
- Clear stock variance and expiry indicators
- Large touch targets
- Fast barcode scanning and quantity entry
- Responsive layouts
- Purposeful, subtle animations
- Loading, success, empty, error, and offline/network states

Required screens, as appropriate to role:

1. Login and loading
2. Dashboard
3. Task list and details
4. Barcode scanner
5. Product search/manual entry
6. Blind stock count
7. Save and Next Scan
8. Expiry verification
9. Photo/evidence capture
10. Submission confirmation
11. My submissions/history
12. Manager pending-review queue
13. Observation details
14. Approve/recount/reject flow
15. Barcode mapping request/review
16. Profile and logout

Use real API data after integration. Any initial preview data must be clearly identified as test data.

Build reusable Flutter components, maintainable navigation, appropriate state management, and clear separation between UI, API, and business logic.

Handle camera permission denial and unsupported device functionality gracefully.

## 11. Laravel API and Security

Inspect the existing authentication mechanism and reuse it where appropriate.

Implement authenticated APIs for the required workflows, following existing routing and response conventions.

Requirements:

- Server-side authentication and authorization
- Role and branch-level access enforcement
- Request validation
- Safe database queries
- Protection against mass assignment
- No unauthorized modification of approval status or inventory values
- Pagination for large lists
- Appropriate database indexes
- Avoidance of N+1 queries
- Consistent error responses
- Safe image upload and authorized retrieval
- Rate limiting where appropriate
- No sensitive stack traces or SQL details in API responses
- Safe authentication token handling in Flutter
- No hard-coded credentials or privileged secrets
- HTTPS for deployed API communication

Do not introduce a second authentication system unnecessarily.

Document the final endpoints, permissions, request/response examples, validation rules, and error behavior.

## 12. Security and Quality Checks

Inspect and address security risks introduced by the new module, including:

- Broken authentication or authorization
- Cross-branch data leakage
- Unauthorized access to another user's observations
- Blind-count quantity exposure
- SQL injection and unsafe query construction
- Mass assignment
- Insecure file uploads
- Duplicate approvals or submissions
- Token leakage and sensitive logging
- Unsafe configuration
- Relevant dependency vulnerabilities

Do not claim the application is secure or vulnerability-free without evidence.

Preserve existing security architecture unless a change is necessary and properly reviewed.

## 13. Automated Testing

Use the existing test frameworks and conventions. Add focused tests for the new functionality.

### Laravel unit tests

Test isolated business logic such as:

- Quantity variance calculation
- Approval eligibility
- Workflow state transitions
- Expiry classification
- Validation and duplicate protection

### Laravel API/feature tests

Test:

- Authentication and authorization
- Branch isolation
- Blind-count API responses
- Product and batch lookup
- Observation submission
- Approval, recount, and rejection
- Duplicate requests
- Invalid input
- Transaction rollback
- Stock and ledger integrity

### Flutter unit and widget tests

Test:

- Model serialization
- API response handling
- Validation
- Workflow state transitions
- Login and task screens
- Blind stock count
- Save and Next Scan
- Expiry verification
- Review actions
- Loading, empty, and error states

### Integration tests

Where the environment supports them, test the critical end-to-end flow using a safe test backend.

Do not invent successful results. If a test cannot run because of a missing emulator, device, dependency, or environment configuration, report it as blocked or not run.

## 14. Smoke Testing and Regression Protection

After integration, execute available smoke tests covering:

1. Laravel starts successfully.
2. Database connection and migrations are valid.
3. Login works.
4. Authorized users retrieve permitted tasks.
5. Product/barcode lookup works.
6. Staff submits a blind count.
7. The submission appears in manager review.
8. Staff submission does not change inventory.
9. Recount/rejection does not change inventory.
10. Manager approval changes inventory only through the existing stock workflow.
11. Duplicate approval cannot create duplicate adjustments.
12. Audit and ledger records remain consistent.
13. Flutter launches and handles API responses.
14. Logout and unauthorized access work.
15. Existing POS, purchase, sales, returns, transfers, damage adjustments, and stock reconciliation continue to work.

Run relevant existing regression tests and compare against baseline failures where practical.

Never disable tests or weaken assertions simply to get a passing result.

Report each check as PASS, FAIL, BLOCKED, or NOT RUN.

## 15. Performance and Mobile Compatibility

- Avoid loading the entire inventory for paginated screens.
- Review expensive queries and indexes.
- Handle large product lists efficiently.
- Check keyboard overlap, text overflow, scrolling, dialogs, and safe areas.
- Handle camera permission denial and barcode detection failure.
- Handle timeouts, expired sessions, network loss, and retries.
- Check common screen sizes.
- Review Flutter package compatibility with Android and iOS.
- Run Flutter formatting, analysis, and tests.
- Build and launch Android where the environment permits.
- Attempt iOS build/device checks only when the required Apple development environment is available.

Do not claim a platform build or device test passed unless it was actually executed.

## 16. Out-of-Scope Features

Do not expand the current scope to include separate paid features unless they are explicitly approved:

- Delivery Management
- Dedicated Bluetooth/laser scanner integration
- Additional purchase invoice receiving workflows
- Additional stock transfer/receiving workflows

Camera barcode scanning and manual barcode entry are part of the core scope.

The shared Flutter application must support future iOS delivery, but the current client delivery is Android only.

Reuse existing capabilities where practical and document any scope ambiguity.

## 17. Implementation Phases

Execute these phases sequentially, continuing autonomously where safe.

**Phase 1 — Audit:** Inspect project, database, inventory lifecycle, permissions, routes, tests, dependencies, and existing changes. Produce an architecture and risk summary.

**Phase 2 — Safety:** Verify environment and backup status, identify a safe test database, preserve the working tree, and define rollback procedures.

**Phase 3 — Design:** Define workflow tables, relationships, state transitions, permissions, API contracts, idempotency, and concurrency rules.

**Phase 4 — Flutter foundation:** Create `mobile-app/`, reusable design system, navigation, screens, and platform-aware architecture.

**Phase 5 — Authentication and read APIs:** Implement secure authentication, branch access, product/barcode lookup, batch lookup, and required read-only APIs.

**Phase 6 — Stock counting:** Implement blind counting, quantity entry, Save and Next Scan, submission, and history.

**Phase 7 — Expiry verification:** Implement batch-aware observations, photos/remarks where required, classification, and barcode mapping requests.

**Phase 8 — Task assignment and review:** Implement tasks, manager queue, approve/recount/reject, permissions, and audit history.

**Phase 9 — Inventory integration:** Apply approved corrections through existing inventory services, using current-stock revalidation, transactions, locking where appropriate, and duplicate protection.

**Phase 10 — Dashboard:** Implement real-data summaries, expiry information, history, and appropriate filters.

**Phase 11 — Integration and QA:** Connect Flutter to the actual APIs. Run security checks, automated tests, smoke tests, and existing regression checks.

**Phase 12 — Handover:** Prepare Android test build where possible, setup documentation, API documentation, database/workflow documentation, testing instructions, known issues, and rollback guidance.

Do not mark a phase complete solely because files exist. Verify its implementation and tests to the extent possible.

## 18. Autonomous Overnight Execution Rules

I may leave Claude working overnight and test the application manually the following morning.

- Continue through safe, well-defined phases without unnecessary confirmation requests.
- Inspect before editing.
- Make focused changes.
- Run relevant checks after each major integration.
- Fix understood failures and retest.
- Keep a progress report updated.
- Continue with independent safe work if a phase is blocked.
- Do not make risky assumptions to finish faster.
- Do not delete data or bypass safety gates.
- Do not push, commit, deploy, or publish.
- Do not claim completion when work is incomplete.

If a critical decision is unclear, document it, avoid the unsafe operation, and continue with safe tasks.

## 19. Required Documentation

Create or update these files in the project root:

### `OVERNIGHT_PROGRESS_REPORT.md`

Include:
- Phase-by-phase completion status
- Files created and modified
- Migrations created and executed
- Tests actually executed and their results
- Security and performance findings
- Android and iOS verification status
- Known issues and blockers
- Existing functionality not regression-tested
- Exact manual testing steps for the next morning

### `URBANPETS_API_DOCUMENTATION.md`

Document implemented endpoints, authentication, permissions, request/response examples, validation, and errors.

### `URBANPETS_DATABASE_AND_WORKFLOW.md`

Document the new schema, relationships, status transitions, approval logic, and inventory integration.

### `URBANPETS_LOCAL_SETUP_AND_TESTING.md`

Provide exact local setup commands, safe migration instructions, app run commands, configuration requirements, and manual test scenarios.

Inspect existing documents before editing; do not blindly overwrite useful content.

## 20. Final Acceptance and Report

Before declaring the module ready for review, verify that:

- Blind counting hides system quantity from staff.
- Staff submissions do not change stock.
- Rejection and recount do not change stock.
- Approved corrections use current stock and existing inventory services.
- Duplicate approvals cannot create duplicate adjustments.
- Authorization and branch isolation are enforced.
- Expiry observations do not silently modify official batch data.
- Critical tests have actual results.
- Existing UrbanPOS workflows have been regression-tested where possible.
- Flutter is integrated with real APIs.
- Android build/test status is reported accurately.
- iOS compatibility and any untested platform work are documented.
- Known bugs and unresolved decisions are clearly listed.

Use PASS, FAIL, BLOCKED, or NOT RUN for test status. Separate implementation completion from testing completion.

At the end, provide:
1. Overall implementation status.
2. Phase-by-phase status.
3. Important files changed.
4. Database and migration status.
5. Actual test commands and results.
6. Security and regression findings.
7. Android build and runtime status.
8. iOS compatibility/build status.
9. Outstanding blockers and known issues.
10. Exact instructions for my morning manual testing.

Do not commit, push, deploy, or publish automatically.

## 21. Start Now

Begin with Phase 1: audit the existing UrbanPOS project.

Do not immediately create tables or rewrite business logic. First understand the actual codebase, inventory lifecycle, authentication, permissions, database schema, test environment, and existing working-tree changes.

Then proceed sequentially through the phases, implementing as much as is safely achievable.

**Priority order:**

1. Protect existing UrbanPOS and its data.
2. Maintain a single authoritative inventory source.
3. Implement correct stock and expiry verification workflows.
4. Integrate manager approval safely with the existing stock ledger.
5. Build the premium Flutter Android/iOS-compatible application.
6. Enforce security and test critical business rules.
7. Run smoke and regression checks.
8. Leave complete, honest documentation for morning testing.

**Final instruction: Work autonomously wherever safe, but never sacrifice data integrity, security, existing functionality, or truthful test reporting just to finish overnight.**