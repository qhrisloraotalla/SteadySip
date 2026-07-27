<?php
include "chartsLogic.php";

$proc->inventoryNotifs($conn);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage</title>
    <!-- <link rel="stylesheet" href="homepage.css" /> -->
    <!-- <link rel="stylesheet" href="sidebar.css"> -->
    <link rel="stylesheet" href="homepage.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>




</head>
<body>

    <!-- HEADER -->
    <div class="content">
        <div class="dashboard-header-container">
            <h2>Forty 31 Dashboard</h2>

            <div class="dashboard-whole">

                <!-- SALES & PROFIT OVERVIEW -->
                <section class="section">
                    <h3>Sales & Profit Overview</h3>
                    <!-- <button onclick="openPrintModal()" class="print-btn">📄 Generate PDF</button> -->
                    <div class="form-div">
                        
                        <form method="get" id="viewForm">
                            <label for="view">View By: </label> 
                            <select name="view" id="view" onchange="document.getElementById('viewForm').submit()">
                                <option value="daily" <?php if ($view == 'daily') echo 'selected'; ?>>Daily</option>
                                <option value="weekly" <?php if ($view == 'weekly') echo 'selected'; ?>>Weekly</option>
                                <option value="monthly" <?php if ($view == 'monthly') echo 'selected'; ?>>Monthly</option>
                            </select>
                        </form>
                    </div>
                    <div class="chart-row">

                        <div class="chart-container large">
                            <canvas id="spendingChart"></canvas>
                        </div>

                        <div class="chart-container mid">
                            <canvas id="salesChart"></canvas>
                        </div>

                    </div>
                </section>

                <!-- MENU PERFORMANCE -->
                <section class="section">
                    <h3>Menu Performance</h3>
                    <div class="chart-row">

                        <div class="chart-container">
                            <canvas id="bestSellingChart"></canvas>
                        </div>

                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>

                    </div>
                </section>

                <!-- CUSTOMER INSIGHTS -->
                <section class="section">
                    <h3>Customer & Transaction Insights</h3>
                    <div class="chart-row">

                        <div class="chart-container">
                            <canvas id="paymentChart"></canvas>
                        </div>

                        <div class="chart-container">
                            <canvas id="serviceModeChart"></canvas>
                        </div>

                    </div>
                </section>

            </div>
        </div>
    </div>

    <!-- PRINT OPTIONS MODAL -->
    <div id="printModal" class="modal">
        <div class="modal-content">
            <h3>Generate Report</h3>

            <label>View By:</label>
            <select id="reportType" onchange="updateDateInput()">
                <option value="" disabled selected>Select...</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
            </select>

            <div id="dateInputContainer" style="margin-top:10px;"></div>

            <div style="margin-top:15px;">
                <button class="confirm-btn" onclick="generateReport()" style="margin-right:5px;">Generate</button>
                <button class="cancel-btn" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>


       
<!-- <div id="printArea" style="position: absolute; left: -9999px; top: -9999px; width: 400px; height: auto;">
    <div class="pdf-header" style="margin:0; padding:0;">
        <h2 style="margin:0; padding:0; font-size:14pt; line-height:1.2;">SteadySip Report</h2>
    </div>

    <!-- Menu Performance -->
    <!-- <section style="margin:0; padding:0;">
        <h3 style="margin:2px 0; padding:0; font-size:12pt;">Menu Performance Summary</h3>
        <canvas id="print_bestSellingChart" width="200" height="200"></canvas>
        <canvas id="print_categoryChart" width="200" height="200"></canvas>
    </section> -->

    <!-- Customer & Transaction Insights -->
    <!-- <section style="margin:0; padding:0;">
        <h3 style="margin:2px 0; padding:0; font-size:12pt;">Customer & Transaction Insights</h3>
        <canvas id="print_paymentChart" width="200" height="200"></canvas>
        <canvas id="print_serviceModeChart" width="200" height="200"></canvas>
    </section> -->

    <!-- <div class="pdf-footer" style="margin:0; padding:0;">
        <center><p style="margin:0; padding:0; font-size:10pt;">SteadySip</p></center>
    </div>
</div> -->






<!-- ===================== CHARTS JS ===================== -->
<script>

//     let printSpendingChart = null;
//     let printBestSellingChart = null;
//     const printCharts = {};

//     function openPrintModal() {
//         document.getElementById("printModal").style.display = "flex";
//     }

//     function closeModal() {
//         document.getElementById("printModal").style.display = "none";
//     }

//     function updateDateInput() {
//         const type = document.getElementById("reportType").value;
//         const container = document.getElementById("dateInputContainer");
//         container.innerHTML = "";

//         if(type === "daily") {
//             container.innerHTML = `<label>Select Date:</label><input type="date" id="selectValue">`;
//         }
//         if(type === "weekly") {
//             container.innerHTML = `<label>Select Week:</label><input type="week" id="selectValue">`;
//         }
//         if(type === "monthly") {
//             container.innerHTML = `<label>Select Month:</label><input type="month" id="selectValue">`;
//         }
//         if(type === "yearly") {
//             let options = "";
//             for(let y = new Date().getFullYear(); y >= 2020; y--){
//                 options += `<option value="${y}">${y}</option>`;
//             }
//             container.innerHTML = `<label>Select Year:</label><select id="selectValue">${options}</select>`;
//         }
//     }

// function renderPrintCharts() {
//     const configs = [
//         { id: 'print_bestSellingChart', type: 'bar', labels: itemNames, data: quantities, label: 'Total Sold' },
//         { id: 'print_categoryChart', type: 'doughnut', labels: categories, data: sales, label: 'Sales by Category' },
//         { id: 'print_paymentChart', type: 'pie', labels: methods, data: totals2, label: 'Sales by Payment Method' },
//         { id: 'print_serviceModeChart', type: 'pie', labels: orderTypes, data: totals4, label: 'Most Popular Service Mode' }
//     ];

//     configs.forEach(cfg => {
//         const canvas = document.getElementById(cfg.id);

//         // Destroy previous chart instance if exists
//         if (printCharts[cfg.id]) printCharts[cfg.id].destroy();

//         printCharts[cfg.id] = new Chart(canvas.getContext('2d'), {
//             type: cfg.type,
//             data: {
//                 labels: cfg.labels,
//                 datasets: [{
//                     label: cfg.label,
//                     data: cfg.data,
//                     backgroundColor: cfg.type === 'bar'
//                         ? 'rgba(75, 192, 192, 0.6)'
//                         : cfg.labels.map(() => `hsl(${Math.random() * 360}, 70%, 65%)`),
//                     borderColor: '#fff',
//                     borderWidth: 1
//                 }]
//             },
//             options: {
//                 responsive: false,
//                 maintainAspectRatio: false,
//                 layout: {
//                     padding: 5
//                 }
//             }
//         });
//     });
// }


// async function generateReport() {
//     const { jsPDF } = window.jspdf;
//     const pdf = new jsPDF('p', 'mm', 'a4');

//     // 1️⃣ Make the hidden print area temporarily visible offscreen
//     const printArea = document.getElementById('printArea');
//     printArea.style.display = 'block';
//     printArea.style.position = 'absolute';
//     printArea.style.left = '-9999px';
//     printArea.style.top = '-9999px';
//     printArea.style.width = '800px';
//     printArea.style.height = 'auto';

//     // 2️⃣ Render print charts
//     renderPrintCharts();

//     // 3️⃣ Add title
//     pdf.setFontSize(16);
//     pdf.text("SteadySip Report", 105, 15, { align: "center" });

//     // 4️⃣ Convert each canvas to image and add to PDF
//     const printCanvasIds = [
//         'print_bestSellingChart',
//         'print_categoryChart',
//         'print_paymentChart',
//         'print_serviceModeChart'
//     ];

//     let pdfWidth = 80; // smaller width for PDF
//     // Add title
//     pdf.setFontSize(16);
//     pdf.text("SteadySip Report", 105, 15, { align: "center" });

//     // Start charts right after header
//     let yOffset = 25; // reduced from 20 to bring charts closer


//     for (let id of printCanvasIds) {
//         const canvas = document.getElementById(id);
//         const canvasImage = await html2canvas(canvas, { backgroundColor: "#fff" });
//         const imgData = canvasImage.toDataURL("image/png");
//         const imgProps = pdf.getImageProperties(imgData);
//         const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

//         if (yOffset + pdfHeight > pdf.internal.pageSize.getHeight()) {
//             pdf.addPage();
//             yOffset = 20;
//         }

//         pdf.addImage(imgData, 'PNG', 10, yOffset, pdfWidth, pdfHeight);
//         yOffset += pdfHeight + 10;
//     }


//     // 5️⃣ Fetch orders data for table
//     const type = document.getElementById('reportType').value || 'daily';
//     const value = document.getElementById('selectValue')?.value || new Date().toISOString().slice(0, 10);

//     let res;
//     try {
//         res = await fetch(`printData.php?type=${type}&value=${value}`);
//         const data = await res.json();

//         if (data.error) {
//             alert("Error fetching data: " + data.error);
//             return;
//         }

//         // 6️⃣ Generate table with jsPDF AutoTable
//         pdf.autoTable({
//             head: [['ID', 'Customer', 'Type', 'Payment', 'Discount', 'Total']],
//             body: data.orders.map(o => [
//                 o.order_id,
//                 o.customer_name,
//                 o.type,
//                 o.payment_method,
//                 o.discount,
//                 o.total_amount
//             ]),
//             startY: yOffset,
//             theme: 'grid',
//             headStyles: { fillColor: [196, 154, 108] },
//             margin: { left: 10, right: 10 }
//         });

//     } catch (err) {
//         alert("Failed to fetch JSON: " + err.message);
//         return;
//     }

//     // 7️⃣ Restore hidden print area
//     printArea.style.display = 'none';

//     // 8️⃣ Open PDF in new tab
//     window.open(pdf.output('bloburl'), '_blank');
// }



    // Get PHP data
    const labels = <?php echo $dates_json; ?>;
    const data = <?php echo $totals_json; ?>;

    const view = "<?php echo $view; ?>";


    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Sales (₱)',
                data: data,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Sales (₱)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Sales (' + view.charAt(0).toUpperCase() + view.slice(1) + ')'
                }
            }

        }
    });


    const categories = <?php echo $categories_json; ?>;
    const sales = <?php echo $sales_json; ?>;



    const ctx2 = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: categories,
            datasets: [{
                label: 'Total Sales (₱)',
                data: sales,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)',
                    'rgba(255, 159, 64, 0.6)'
                ],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 15,
                        padding: 15
                    }
                },
                title: {
                    display: true,
                    text: 'Total Sales by Category'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `₱${parseFloat(context.raw).toLocaleString()}`;
                        }
                    }
                }
            }
        }
    });


    const itemNames = <?php echo $item_names_json; ?>;
    const quantities = <?php echo $quantities_json; ?>;

    const ctx3 = document.getElementById('bestSellingChart').getContext('2d');
    new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: itemNames,
            datasets: [{
                label: 'Total Sold',
                data: quantities,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)'
                ],
                borderColor: '#fff',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantity Sold'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Menu Item'
                    }
                }
            },
            plugins: {
                legend: { display: false },
                title: {
                    display: true,
                    text: 'Top 5 Best-Selling Items'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.raw} sold`;
                        }
                    }
                }
            }
        }
    });

    const methods = <?php echo $methods_json; ?>;
    const totals2 = <?php echo $totals_json2; ?>;

    // Optional: Generate random pastel colors
    const colors = methods.map(() => 
        `hsl(${Math.random() * 360}, 70%, 65%)`
    );

    const ctx4 = document.getElementById('paymentChart').getContext('2d');
    new Chart(ctx4, {
        type: 'pie',
        data: {
            labels: methods,
            datasets: [{
                data: totals2,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#464646ff',
                        boxWidth: 15,
                        padding: 10
                    }
                },
                title: {
                    display: true,
                    text: 'Sales by Payment Method'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            return `${label}: ${value} transactions`;
                        }
                    }
                }
            }
        }
    });


    const labels2 = <?php echo $periods_json; ?>;
    const totals3 = <?php echo $totals_json3; ?>;

    const ctx5 = document.getElementById('spendingChart').getContext('2d');
    new Chart(ctx5, {
        type: 'line',
        data: {
            labels: labels2.map(l => l),
            datasets: [{
                label: '<?php echo $label_title; ?> (₱)',
                data: totals3,
                borderColor: 'rgba(99, 97, 158, 1)',
                backgroundColor: 'rgba(110, 124, 177, 0.2)',
                fill: true,
                borderWidth: 2,
                pointRadius: 4,
                pointBackgroundColor: 'rgba(96, 99, 153, 0.44)',
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount Spent (₱)',
                        color: '#464646ff'
                    },
                    ticks: { color: '#464646ff' },
                    grid: { color: '#aaaaaaff' }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Period',
                        color: '#464646ff'
                    },
                    ticks: { color: '#464646ff' },
                    grid: { color: '#aaaaaaff' }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: '<?php echo $label_title; ?>',
                    color: '#6c6c6cff',
                    font: { size: 16 }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `₱${context.raw.toLocaleString()}`;
                        }
                    }
                },
                legend: {
                    display: false
                }
            }
        }
    });

    const orderTypes = <?php echo $types_json; ?>;
    const totals4 = <?php echo $totals_json4; ?>;

    const ctx6 = document.getElementById('serviceModeChart').getContext('2d');
    new Chart(ctx6, {
        type: 'pie',
        data: {
            labels: orderTypes,
            datasets: [{
                data: totals4,
                backgroundColor: [
                    'rgba(75, 192, 192, 0.8)',  // teal (for dine-in)
                    'rgba(255, 205, 86, 0.8)',  // yellow (for take-out)
                    'rgba(255, 99, 132, 0.8)'   // fallback (for others if any)
                ],
                borderColor: [
                    'rgba(255, 255, 255, 0.9)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Most Popular Service Mode',
                    color: '#464646ff',
                    font: { size: 16 }
                },
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#464646ff',
                        boxWidth: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.chart._metasets[0].total;
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} orders (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });



</script>

</body>
</html>
