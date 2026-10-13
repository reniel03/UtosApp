const transactionDetailStorageKey = 'utosapp.admin.selected-transaction.v1';
const transactionDetailBack = document.getElementById('transactionDetailBack');

let transactionDetails;
try {
    transactionDetails = JSON.parse(sessionStorage.getItem(transactionDetailStorageKey) || 'null');
} catch (error) {
    transactionDetails = null;
}

if (!transactionDetails) {
    window.location.replace('/admin-transactions');
} else {
    const setText = (id, value) => {
        document.getElementById(id).textContent = value || '';
    };
    const status = String(transactionDetails.status || 'Completed');
    const statusClass = status.toLowerCase().replace(/[^a-z0-9]+/g, '-');

    document.title = `${transactionDetails.title || 'Transaction'} - UtosApp`;
    setText('transactionTitle', transactionDetails.title || 'Transaction');
    setText('transactionKind', transactionDetails.type === 'platform' ? 'Platform Earnings' : 'Payment');
    setText('transactionAmount', transactionDetails.amount || 'Not available');
    setText('transactionStatus', status);
    setText('transactionStatusDetail', status);
    setText('teacherRole', transactionDetails.teacherRole || 'Teacher');
    setText('teacherName', transactionDetails.teacher || 'Teacher');
    setText('studentRole', transactionDetails.studentRole || 'Student');
    setText('studentName', transactionDetails.student || 'Student');
    const initials = (name) => String(name || '').trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
    setText('teacherAvatar', initials(transactionDetails.teacher || 'Teacher'));
    setText('studentAvatar', initials(transactionDetails.student || 'Student'));
    setText('transactionType', transactionDetails.type === 'platform' ? 'Platform earning' : 'Task payment');
    setText('transactionDate', transactionDetails.date || 'Not available');
    document.getElementById('transactionStatus').classList.add(`status-${statusClass}`);

    if (transactionDetails.transactionNumber) {
        setText('transactionNumber', transactionDetails.transactionNumber);
        document.getElementById('transactionNumberRow').hidden = false;
    }
    if (transactionDetails.taskName) {
        setText('taskName', transactionDetails.taskName);
        document.getElementById('taskNameRow').hidden = false;
    }

    transactionDetailBack.addEventListener('click', () => {
        const returnUrl = String(transactionDetails.returnUrl || '/admin-transactions');
        window.location.href = returnUrl.startsWith('/admin-transactions') ? returnUrl : '/admin-transactions';
    });
}