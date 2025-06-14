<?php
require 'auth_check.php';
require 'db.php';

// Get user's categories
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$stmt->execute([$_SESSION["user_id"]]);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Expense Tracker</title>
    <link rel="stylesheet" href="styleDashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h1>Expense Tracker</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?> | 
        <a href="manage_categories.php">Manage Categories</a> | 
        <a href="logout.php">Logout</a>
    </p>

    <form id="expenseForm" onsubmit="return false;">
        <input type="text" name="description" placeholder="Description" required>
        <input type="number" name="amount" placeholder="Amount" step="0.01" min="0.01" required>
        <input type="date" name="date" required>
        <select name="category" required>
            <?php foreach ($categories as $category): ?>
                <option value="<?= $category["id"] ?>">
                    <?= htmlspecialchars($category["name"]) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" id="submitBtn">Add</button>
    </form>

    <div class="controls">
        <div class="sort-controls">
            <button class="sort-btn" data-sort="date" data-order="desc">Date ↓</button>
            <button class="sort-btn" data-sort="amount" data-order="desc">Amount ↓</button>
            <button class="sort-btn" data-sort="category" data-order="asc">Category ↑</button>
            <button class="sort-btn" data-sort="description" data-order="asc">Name ↑</button>
        </div>
        <div class="filter-controls">
            <div class="date-range-controls">
                <select id="dateRange">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                    <option value="custom">Custom Range</option>
                </select>
                <div id="customDateRange" style="display: none;">
                    <input type="date" id="startDate">
                    <input type="date" id="endDate">
                </div>
            </div>
            <select id="categoryFilter">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category["id"] ?>">
                        <?= htmlspecialchars($category["name"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="chart-container">
        <div class="chart-wrapper">
            <div class="chart-loading">Loading category chart...</div>
            <canvas id="categoryChart"></canvas>
        </div>
        <div class="chart-wrapper">
            <div class="chart-loading">Loading timeline chart...</div>
            <canvas id="timelineChart"></canvas>
        </div>
    </div>

    <h2>Expenses</h2>
    <div id="expenses" class="expenses-list">Loading expenses...</div>

    <script>
    let isSubmitting = false;
    let currentSort = { field: 'date', order: 'desc' };
    let currentCategory = '';
    let currentDateRange = { type: 'all', start: null, end: null };
    let categoryChart = null;
    let timelineChart = null;

    // Date range functionality
    document.getElementById('dateRange').addEventListener('change', function() {
        const customRangeDiv = document.getElementById('customDateRange');
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        
        if (this.value === 'custom') {
            customRangeDiv.style.display = 'inline-block';
            currentDateRange.type = 'custom';
        } else {
            customRangeDiv.style.display = 'none';
            currentDateRange.type = this.value;
            currentDateRange.start = null;
            currentDateRange.end = null;
        }
        loadExpenses();
        loadCharts();
    });

    document.getElementById('startDate').addEventListener('change', function() {
        currentDateRange.start = this.value;
        loadExpenses();
        loadCharts();
    });

    document.getElementById('endDate').addEventListener('change', function() {
        currentDateRange.end = this.value;
        loadExpenses();
        loadCharts();
    });

    // Sort buttons functionality
    document.querySelectorAll('.sort-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const field = this.dataset.sort;
            const currentOrder = this.dataset.order;
            const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            
            // Update button text and data
            this.dataset.order = newOrder;
            this.textContent = `${field.charAt(0).toUpperCase() + field.slice(1)} ${newOrder === 'asc' ? '↑' : '↓'}`;
            
            // Update active state
            document.querySelectorAll('.sort-btn').forEach(b => {
                if (b !== this) {
                    b.classList.remove('active');
                    // Reset other buttons to default order
                    if (b.dataset.sort === 'date') b.dataset.order = 'desc';
                    if (b.dataset.sort === 'amount') b.dataset.order = 'desc';
                    if (b.dataset.sort === 'category') b.dataset.order = 'asc';
                    if (b.dataset.sort === 'description') b.dataset.order = 'asc';
                }
            });
            this.classList.add('active');
            
            currentSort = { field, order: newOrder };
            loadExpenses();
        });
    });

    // Category filter functionality
    document.getElementById('categoryFilter').addEventListener('change', function() {
        currentCategory = this.value;
        loadExpenses();
        loadCharts();
    });

    // Prevent form double submission
    const form = document.getElementById('expenseForm');
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        
        if (isSubmitting) {
            console.log('Already submitting, ignoring...');
            return;
        }

        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        isSubmitting = true;
        
        try {
            const formData = new FormData(this);
            const response = await fetch('add_expense.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.text();
            
            if (response.ok && result === 'success') {
                this.reset();
                await Promise.all([loadExpenses(), loadCharts()]);
            } else {
                alert('Failed to add expense: ' + result);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to add expense. Please try again.');
        } finally {
            submitBtn.disabled = false;
            isSubmitting = false;
        }
    });

    async function loadExpenses() {
        try {
            let url = `view_expenses.php?sort=${currentSort.field}&order=${currentSort.order}&category=${currentCategory}`;
            
            if (currentDateRange.type !== 'all') {
                url += `&date_range=${currentDateRange.type}`;
                if (currentDateRange.start) url += `&start_date=${currentDateRange.start}`;
                if (currentDateRange.end) url += `&end_date=${currentDateRange.end}`;
            }

            const response = await fetch(url);
            const html = await response.text();
            document.getElementById('expenses').innerHTML = html;

            // Attach delete event listeners
            document.querySelectorAll('.delete-expense').forEach(button => {
                button.addEventListener('click', async function() {
                    if (confirm('Are you sure you want to delete this expense?')) {
                        const id = this.dataset.id;
                        const response = await fetch('view_expenses.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `delete_id=${id}`
                        });
                        
                        if (response.ok) {
                            loadExpenses();
                            loadCharts();
                        } else {
                            alert('Failed to delete expense');
                        }
                    }
                });
            });
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('expenses').innerHTML = 'Error loading expenses';
        }
    }

    async function loadCharts() {
        try {
            let url = 'view_expenses.php?json=1';
            
            if (currentDateRange.type !== 'all') {
                url += `&date_range=${currentDateRange.type}`;
                if (currentDateRange.start) url += `&start_date=${currentDateRange.start}`;
                if (currentDateRange.end) url += `&end_date=${currentDateRange.end}`;
            }

            // Add category parameter if selected
            if (currentCategory) {
                url += `&category=${currentCategory}`;
            }

            const response = await fetch(url);
            if (!response.ok) {
                throw new Error('Failed to load chart data');
            }
            const data = await response.json();
            
            // Category Chart
            const categoryCtx = document.getElementById('categoryChart');
            if (!categoryCtx) {
                console.error('Category chart canvas not found');
                return;
            }

            // Remove loading message
            const categoryLoading = categoryCtx.parentElement.querySelector('.chart-loading');
            if (categoryLoading) {
                categoryLoading.remove();
            }

            if (categoryChart) {
                categoryChart.destroy();
            }

            const categoryColors = [
                '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e',
                '#e74a3b', '#858796', '#5a5c69', '#2e59d9'
            ];

            categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Expenses by Category',
                        data: data.amounts || [],
                        backgroundColor: categoryColors,
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Expenses by Category',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: 20
                        },
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    return `${label}: $${value.toFixed(2)}`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });

            // Timeline Chart
            const timelineCtx = document.getElementById('timelineChart');
            if (!timelineCtx) {
                console.error('Timeline chart canvas not found');
                return;
            }

            // Remove loading message
            const timelineLoading = timelineCtx.parentElement.querySelector('.chart-loading');
            if (timelineLoading) {
                timelineLoading.remove();
            }

            if (timelineChart) {
                timelineChart.destroy();
            }

            timelineChart = new Chart(timelineCtx, {
                type: 'line',
                data: {
                    labels: data.dates || [],
                    datasets: [{
                        label: 'Daily Expenses',
                        data: data.dailyAmounts || [],
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.05)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#4e73df',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Expenses Over Time',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: 20
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw || 0;
                                    return `$${value.toFixed(2)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value;
                                },
                                font: {
                                    size: 12
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error loading charts:', error);
            document.querySelectorAll('.chart-wrapper').forEach(wrapper => {
                const loading = wrapper.querySelector('.chart-loading');
                if (loading) {
                    loading.textContent = 'Error loading chart data';
                    loading.style.color = 'red';
                }
            });
        }
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', function() {
        loadExpenses();
        loadCharts();
    });
    </script>
</body>
</html>
