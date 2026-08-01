// AJAX Polling for Notifications

document.addEventListener('DOMContentLoaded', () => {
  // --- Theme Toggle Control ---
  const themeToggleBtn = document.getElementById('themeToggleBtn');
  const themeToggleIcon = document.getElementById('themeToggleIcon');
  
  if (themeToggleBtn && themeToggleIcon) {
    // Set initial icon based on actual active class
    if (document.documentElement.classList.contains('light-theme')) {
      themeToggleIcon.className = 'fa-solid fa-sun';
    } else {
      themeToggleIcon.className = 'fa-solid fa-moon';
    }
    
    themeToggleBtn.addEventListener('click', () => {
      const isLight = document.documentElement.classList.toggle('light-theme');
      if (isLight) {
        themeToggleIcon.className = 'fa-solid fa-sun';
        localStorage.setItem('theme', 'light');
      } else {
        themeToggleIcon.className = 'fa-solid fa-moon';
        localStorage.setItem('theme', 'dark');
      }
    });
  }

  // --- Notifications Polling ---
  const notifBellBtn = document.getElementById('notifBellBtn');
  const notifDropdown = document.getElementById('notifDropdown');
  const notifBadge = document.getElementById('notifBadge');
  const notifList = document.getElementById('notifList');
  const markReadBtn = document.getElementById('markReadBtn');
  
  // Resolve root prefix from current path depth
  const inSubFolder = window.location.pathname.includes('/student/') || 
                      window.location.pathname.includes('/staff/') || 
                      window.location.pathname.includes('/admin/');
  const apiPath = inSubFolder ? '../api/notifications.php' : 'api/notifications.php';

  // Toggle Dropdown
  if (notifBellBtn && notifDropdown) {
    notifBellBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      notifDropdown.classList.toggle('show');
    });

    // Close dropdown on click outside
    document.addEventListener('click', (e) => {
      if (!notifDropdown.contains(e.target) && !notifBellBtn.contains(e.target)) {
        notifDropdown.classList.remove('show');
      }
    });
  }

  // Fetch Notifications
  function fetchNotifications() {
    fetch(`${apiPath}`)
      .then(response => {
        if (!response.ok) throw new Error("Network error");
        return response.json();
      })
      .then(data => {
        if (data.success) {
          updateNotifUI(data.unread_count, data.notifications);
        }
      })
      .catch(error => console.error("Error fetching notifications:", error));
  }

  // Update UI Elements
  function updateNotifUI(unreadCount, list) {
    // Update Badge
    if (unreadCount > 0) {
      notifBadge.textContent = unreadCount;
      notifBadge.classList.remove('hidden');
    } else {
      notifBadge.classList.add('hidden');
    }

    // Update List
    if (!list || list.length === 0) {
      notifList.innerHTML = '<li class="notif-empty">No notifications</li>';
      return;
    }

    notifList.innerHTML = list.map(item => {
      const isUnread = item.read_status == 0 ? 'unread' : '';
      return `
        <li class="notif-item ${isUnread}">
          ${escapeHtml(item.message)}
          <span class="notif-time">${formatTime(item.created_at)}</span>
        </li>
      `;
    }).join('');
  }

  // Mark All as Read
  if (markReadBtn) {
    markReadBtn.addEventListener('click', (e) => {
      e.preventDefault();
      fetch(`${apiPath}?action=mark_all_read`, { method: 'POST' })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            notifBadge.classList.add('hidden');
            // Fetch again to refresh list status colors
            fetchNotifications();
          }
        })
        .catch(error => console.error("Error marking read:", error));
    });
  }

  // Helper Escaper
  function escapeHtml(str) {
    return str.replace(/&/g, "&amp;")
              .replace(/</g, "&lt;")
              .replace(/>/g, "&gt;")
              .replace(/"/g, "&quot;")
              .replace(/'/g, "&#039;");
  }

  // Helper Time Formatter (returns clean relative or short date format)
  function formatTime(sqlTimestamp) {
    if (!sqlTimestamp) return '';
    const date = new Date(sqlTimestamp.replace(' ', 'T'));
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHrs = Math.floor(diffMins / 60);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHrs < 24) return `${diffHrs}h ago`;
    
    return date.toLocaleDateString(undefined, {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
  }

  // Initial Fetch & Start Polling (12 seconds interval)
  fetchNotifications();
  setInterval(fetchNotifications, 12000);
});
