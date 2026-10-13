const toast = document.getElementById('toast');
const allTransactionsView = new URLSearchParams(window.location.search).get('view') === 'all';
let toastTimer;
let selectedType = 'total-task-payments';

if (allTransactionsView) {
    document.body.classList.add('all-transactions-view');
    document.getElementById('transactionsBack').hidden = false;
    document.querySelector('.transactions-hero small').textContent = 'View and manage all user transactions.';
    document.getElementById('transactionSearch').placeholder = 'Search by task title, student, or teacher...';
    document.getElementById('transactionSearch').setAttribute('aria-label', 'Search by task title, student, or teacher');
    document.getElementById('transactionFilterIcon').innerHTML = '<path d="M4 5h16l-6.5 7.5V19l-3 1v-7.5L4 5Z"></path>';
    const statusFilter = document.getElementById('dateFilter');
    statusFilter.setAttribute('aria-label', 'Filter by status');
    statusFilter.replaceChildren(...[
        ['all', 'All Status'],
        ['paid', 'Paid'],
        ['completed', 'Completed'],
        ['pending', 'Pending'],
        ['failed', 'Failed']
    ].map(([value, label]) => new Option(label, value)));
    document.querySelectorAll('.transaction-stats article small').forEach((label, index) => {
        label.textContent = ['Total Transactions', 'Completed', 'Pending', 'Failed'][index];
    });
    document.querySelector('.section-heading h2').textContent = 'All Transactions';
    document.getElementById('transactionsBack').addEventListener('click', () => {
        window.location.href = '/admin-transactions';
    });
    selectedType = 'total-task-payments';
}

function showToast(message) {
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2200);
}

function setTransactionDirection(transaction) {
    const detail = transaction.querySelector('.transaction-details small');
    if (!detail) return;

    if (transaction.dataset.type === 'platform') {
        const transactionNumber = transaction.dataset.transactionNumber || 'TXN-0000';
        const taskName = transaction.dataset.taskName || 'Task';
        const teacher = transaction.dataset.teacher || 'Teacher';
        const student = transaction.dataset.student || 'Student';
        if (allTransactionsView) {
            const details = [
                `Txn# ${transactionNumber}`,
                `Task: ${taskName}`,
                `Teacher: ${teacher}`,
                `Student: ${student}`
            ].map((text) => {
                const line = document.createElement('span');
                line.textContent = text;
                return line;
            });
            detail.replaceChildren(...details);
            return;
        }
        detail.textContent = `Txn# ${transactionNumber} • Task: ${taskName} • Teacher: ${teacher} • Student: ${student}`;
        return;
    }

    const teacher = transaction.dataset.teacher || 'Teacher';
    const teacherRole = transaction.dataset.teacherRole || 'Teacher';
    const student = transaction.dataset.student || 'Student';
    const studentRole = transaction.dataset.studentRole || 'Student';
    if (allTransactionsView) {
        const sender = document.createElement('span');
        sender.textContent = `From: ${teacher} (${teacherRole})`;
        const recipient = document.createElement('span');
        recipient.textContent = `Send to ${student} (${studentRole})`;
        detail.replaceChildren(sender, recipient);
        return;
    }
    detail.textContent = `From: ${teacher} (${teacherRole}) to ${student} (${studentRole})`;
}

function getPlatformEarning(paymentAmount) {
    return paymentAmount < 100 ? 5 : 10;
}

function openTransactionDetails(transaction) {
    const transactionDetails = {
        title: transaction.querySelector('.transaction-details strong')?.textContent.trim() || 'Transaction',
        type: transaction.dataset.type || 'income',
        amount: transaction.querySelector('.amount')?.textContent.trim() || '',
        status: transaction.querySelector('.type')?.textContent.trim() || 'Completed',
        date: transaction.querySelector('.transaction-details time')?.textContent.replace(/\s+/g, ' ').trim() || '',
        transactionNumber: transaction.dataset.transactionNumber || '',
        taskName: transaction.dataset.taskName || '',
        teacher: transaction.dataset.teacher || 'Teacher',
        teacherRole: transaction.dataset.teacherRole || 'Teacher',
        student: transaction.dataset.student || 'Student',
        studentRole: transaction.dataset.studentRole || 'Student',
        returnUrl: `${window.location.pathname}${window.location.search}`
    };

    try {
        sessionStorage.setItem('utosapp.admin.selected-transaction.v1', JSON.stringify(transactionDetails));
        window.location.href = '/admin-transaction-details.html';
    } catch (error) {
        console.error('Unable to open transaction details:', error);
        showToast('Could not open transaction details.');
    }
}

document.querySelectorAll('.transaction-row').forEach((transaction) => {
    const title = transaction.querySelector('.transaction-details strong')?.textContent.trim() || 'transaction';
    transaction.tabIndex = 0;
    transaction.setAttribute('role', 'link');
    transaction.setAttribute('aria-label', `View details for ${title}`);
    transaction.addEventListener('click', () => openTransactionDetails(transaction));
    transaction.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        openTransactionDetails(transaction);
    });
});

function filterTransactions() {
    const query = document.getElementById('transactionSearch').value.trim().toLowerCase();
    const selectedStatus = allTransactionsView ? document.getElementById('dateFilter').value : 'all';
    let visibleCount = 0;
    let totalTaskPayments = 0;
    let totalStudentEarnings = 0;

    document.querySelectorAll('.transaction-row').forEach((transaction) => {
        setTransactionDirection(transaction);

        const status = transaction.querySelector('.type');
        if (status) {
            status.hidden = selectedType === 'transactions';
            if (transaction.dataset.type === 'platform') {
                status.textContent = 'COMPLETED';
            } else if (transaction.dataset.type === 'income') {
                status.textContent = selectedType === 'student-earnings' ? 'RECEIVED' : 'PAID';
            }
        }

        const amount = transaction.querySelector('.amount');
        if (amount) {
            if (!('originalAmount' in transaction.dataset)) {
                transaction.dataset.originalAmount = amount.textContent;
            }
            const taskPaymentAmount = Number(transaction.dataset.taskPaymentAmount);
            const grossAmount = Number(transaction.dataset.originalAmount.replace(/[^\d.-]/g, ''));
            if (transaction.dataset.type === 'income' && Number.isFinite(grossAmount)) {
                totalStudentEarnings += grossAmount;
                totalTaskPayments += grossAmount + getPlatformEarning(grossAmount);
            }
            let displayAmount = transaction.dataset.type === 'platform' && Number.isFinite(taskPaymentAmount)
                ? `+₱${new Intl.NumberFormat('en-PH').format(getPlatformEarning(taskPaymentAmount))}`
                : transaction.dataset.originalAmount;
            if (selectedType === 'total-task-payments' && transaction.dataset.type === 'income' && Number.isFinite(grossAmount)) {
                displayAmount = `₱${new Intl.NumberFormat('en-PH').format(grossAmount + getPlatformEarning(grossAmount))}`;
            }
            amount.textContent = ['total-task-payments', 'transactions'].includes(selectedType)
                ? displayAmount.replace(/^[+-]\s*/, '')
                : displayAmount;
        }

        const matchesQuery = !query || transaction.dataset.search.includes(query);
        const matchesType =
            selectedType === 'all' ||
            (selectedType === 'total-task-payments' && transaction.dataset.type === 'income') ||
            (selectedType === 'student-earnings' && transaction.dataset.type === 'income') ||
            (selectedType === 'platform-earnings' && transaction.dataset.type === 'platform') ||
            (selectedType === 'transactions' && transaction.dataset.type === 'income') ||
            (selectedType === 'income' && transaction.dataset.type === 'income') ||
            (selectedType === 'expense' && transaction.dataset.type === 'expense') ||
            (selectedType === 'fee' && transaction.querySelector('.transaction-icon.fee'));
        const statusText = (status?.textContent || '').trim().toLowerCase();
        const matchesStatus = selectedStatus === 'all' || statusText === selectedStatus;
        const visible = matchesQuery && matchesType && matchesStatus;
        transaction.hidden = !visible;
        if (visible) visibleCount += 1;
    });

    document.getElementById('totalTaskPaymentsTotal').textContent = `P${new Intl.NumberFormat('en-PH').format(totalTaskPayments)}`;
    document.getElementById('studentEarningsTotal').textContent = `P${new Intl.NumberFormat('en-PH').format(totalStudentEarnings)}`;
    const transactionCount = document.querySelectorAll('.transaction-row[data-type="income"]').length;
    document.getElementById('transactionCount').textContent = String(transactionCount);
    const platformEarnings = [...document.querySelectorAll('.transaction-row[data-type="platform"]')]
        .reduce((total, transaction) => total + getPlatformEarning(Number(transaction.dataset.taskPaymentAmount)), 0);
    document.getElementById('platformEarningsTotal').textContent = `P${new Intl.NumberFormat('en-PH').format(platformEarnings)}`;
    if (allTransactionsView) {
        const transactions = [...document.querySelectorAll('.transaction-row')].filter((transaction) => {
            if (selectedType === 'total-task-payments' || selectedType === 'student-earnings' || selectedType === 'transactions') {
                return transaction.dataset.type === 'income';
            }
            if (selectedType === 'platform-earnings') return transaction.dataset.type === 'platform';
            return true;
        });
        const transactionStatuses = transactions.map((transaction) => transaction.querySelector('.type')?.textContent.trim().toLowerCase());
        document.getElementById('totalTaskPaymentsTotal').textContent = String(transactions.length);
        document.getElementById('studentEarningsTotal').textContent = String(transactionStatuses.filter((status) => ['paid', 'received', 'completed'].includes(status)).length);
        document.getElementById('platformEarningsTotal').textContent = String(transactionStatuses.filter((status) => status === 'pending').length);
        document.getElementById('transactionCount').textContent = String(transactionStatuses.filter((status) => status === 'failed').length);
    }
    try {
        localStorage.setItem('utosapp.admin.transactions.snapshot.v2', JSON.stringify({ platformEarnings, transactionCount, updatedAt: Date.now() }));
    } catch (error) {
        console.error('Unable to share transaction totals with the dashboard:', error);
    }
    document.getElementById('emptyState').hidden = visibleCount > 0;
}

document.getElementById('transactionSearch').addEventListener('input', filterTransactions);
document.querySelectorAll('.category-tabs button').forEach((button) => {
    button.addEventListener('click', () => {
        selectedType = button.dataset.type;
        document.querySelectorAll('.category-tabs button').forEach((tab) => tab.classList.toggle('active', tab === button));
        filterTransactions();
    });
});
document.getElementById('dateFilter').addEventListener('change', (event) => {
    if (allTransactionsView) filterTransactions();
    else showToast(`${event.target.selectedOptions[0].textContent} selected.`);
});
document.getElementById('viewAllButton').addEventListener('click', () => {
    window.location.href = '/admin-transactions?view=all';
});
document.querySelectorAll('[data-action]').forEach((button) => {
    button.addEventListener('click', () => showToast(`${button.dataset.action} is ready to configure.`));
});

const requestedType = new URLSearchParams(window.location.search).get('type');
if (['total-task-payments', 'student-earnings', 'platform-earnings', 'transactions'].includes(requestedType)) {
    selectedType = requestedType;
}
document.querySelectorAll('.category-tabs button').forEach((button) => {
    button.classList.toggle('active', button.dataset.type === selectedType);
});

filterTransactions();
