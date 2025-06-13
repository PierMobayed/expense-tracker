fetch('view_expenses.php')
    .then(response => response.json())
    .then(data => {
        const ctx = document.getElementById('expenseChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Expenses by Category',
                    data: data.amounts,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                }]
            }
        });
    });
