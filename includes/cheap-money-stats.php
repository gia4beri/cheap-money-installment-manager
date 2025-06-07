<?php
if (!defined('ABSPATH')) exit;

function cheap_money_statistics_page() {
    ?>
    <div class="wrap">
        <h1>📊 განვადების სტატისტიკა</h1>

        <canvas id="cheapMoneyChart" style="max-width: 600px; margin-top: 30px;"></canvas>

        <div style="margin-top: 40px;">
            <h3>⬇️ სრული რეპორტის გადმოწერა:</h3>
            <a href="<?php echo admin_url('admin-ajax.php?action=download_cheap_money_report&status=on-hold'); ?>" class="button">⌛ ახალი</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=download_cheap_money_report&status=approved'); ?>" class="button">✅ დამტკიცებული</a>
            <a href="<?php echo admin_url('admin-ajax.php?action=download_cheap_money_report&status=unapproved'); ?>" class="button">❌ დაუმტკიცებელი</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        fetch('<?php echo admin_url('admin-ajax.php?action=get_cheap_money_stats'); ?>')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const ctx = document.getElementById('cheapMoneyChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ['ახალი', 'დამტკიცებული', 'დაუმტკიცებელი', 'ლიმიტით'],
                            datasets: [{
                                label: 'შეკვეთების რაოდენობა',
                                data: [
                                    data.data.on_hold,
                                    data.data.approved,
                                    data.data.unapproved,
                                    data.data.with_limit
                                ],
                                backgroundColor: ['#f0ad4e', '#5cb85c', '#d9534f', '#5bc0de']
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: false },
                                title: {
                                    display: true,
                                    text: 'განვადების შეკვეთების სტატისტიკა'
                                }
                            }
                        }
                    });
                }
            });
    });
    </script>
    <?php
}
