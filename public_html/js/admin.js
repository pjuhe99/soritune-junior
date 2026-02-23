/**
 * 소리튠 주니어 영어학교 - 관리쌤/부모 로직
 */
const AdminApp = (() => {
    let adminInfo = null;

    async function init() {
        document.getElementById('btn-logout').addEventListener('click', async () => {
            App.confirm('로그아웃 하시겠습니까?', async () => {
                await App.post('/api/admin.php?action=logout');
                window.location.href = '/admin/login.php';
            }, { formal: true });
        });

        const result = await App.get('/api/admin.php?action=check_session');
        if (!result.logged_in) {
            window.location.href = '/admin/login.php';
            return;
        }

        adminInfo = result.admin;

        // admin_teacher는 전용 페이지(/teacher/)로 리다이렉트
        if (adminInfo.admin_role === 'admin_teacher') {
            window.location.href = '/teacher/';
            return;
        }

        const roleLabels = { admin_teacher: '관리쌤', parent: '부모님' };
        document.getElementById('admin-title').textContent =
            `${adminInfo.admin_name} ${roleLabels[adminInfo.admin_role] || ''}`;

        loadStudents();
    }

    async function loadStudents() {
        const result = await App.get('/api/admin.php?action=my_students');
        if (!result.success) return;

        const container = document.getElementById('content');

        if (result.students.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="empty-state-text">연결된 학생이 없습니다</div></div>';
            return;
        }

        container.innerHTML = `
            <div class="card" style="padding:0; overflow:hidden;">
                ${result.students.map(s => `
                    <div class="list-item" style="cursor:pointer;" data-id="${s.id}">
                        <div class="avatar">${s.name.charAt(0)}</div>
                        <div class="list-item-content">
                            <div class="list-item-title">${s.name}</div>
                            <div class="list-item-subtitle">${s.class_name || ''} ${s.grade ? `/ ${s.grade}` : ''}</div>
                        </div>
                        ${App.coinBadge(s.total_coins)}
                    </div>
                `).join('')}
            </div>
        `;

        container.querySelectorAll('.list-item').forEach(item => {
            item.addEventListener('click', () => loadDashboard(parseInt(item.dataset.id)));
        });
    }

    async function loadDashboard(studentId) {
        App.showLoading();
        const result = await App.get(`/api/admin.php?action=student_dashboard&student_id=${studentId}`);
        App.hideLoading();

        if (!result.success) return;

        const { student, rewards, total_coins, checklists } = result;
        const container = document.getElementById('content');

        const fields = ['zoom_attendance', 'posture_king', 'sound_homework', 'band_mission', 'leader_king'];
        const labels = ['줌출석', '자세왕', '소리과제', '밴드미션', '리더왕'];

        container.innerHTML = `
            <button class="btn btn-secondary btn-sm" onclick="AdminApp.backToList()" style="margin-bottom:16px;">← 목록으로</button>

            <div class="card">
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                    <div class="avatar avatar-lg">${student.name.charAt(0)}</div>
                    <div>
                        <div style="font-size:20px; font-weight:700;">${student.name}</div>
                        <div style="font-size:13px; color:#9E9E9E;">${student.class_name || ''} / ${student.coach_name || ''}</div>
                    </div>
                    ${App.coinBadge(total_coins, 'lg')}
                </div>

                <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:8px; text-align:center;">
                    ${rewards.map(r => `
                        <div style="padding:8px; border-radius:8px; background:#f5f5f5; border-top:3px solid ${r.color};">
                            <div style="font-size:20px; font-weight:800; color:${r.color};">${r.quantity}</div>
                            <div style="font-size:10px; color:#9E9E9E;">${r.name_ko}</div>
                        </div>
                    `).join('')}
                </div>
            </div>

            ${checklists.length > 0 ? `
                <div class="card" style="margin-top:16px;">
                    <div style="font-size:16px; font-weight:700; margin-bottom:12px;">최근 체크리스트</div>
                    <div style="overflow-x:auto;">
                        <table class="data-table" style="min-width:400px;">
                            <thead>
                                <tr>
                                    <th>날짜</th>
                                    ${labels.map(l => `<th style="text-align:center;">${l}</th>`).join('')}
                                </tr>
                            </thead>
                            <tbody>
                                ${checklists.map(c => `
                                    <tr>
                                        <td>${App.formatDate(c.check_date, 'MM/DD')}</td>
                                        ${fields.map(f => `<td style="text-align:center;">${c[f] ? '✅' : '⬜'}</td>`).join('')}
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            ` : ''}
        `;
    }

    function backToList() {
        loadStudents();
    }

    document.addEventListener('DOMContentLoaded', init);

    return { backToList };
})();
