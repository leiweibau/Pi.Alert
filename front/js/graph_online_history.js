function graph_online_history_main(online_history_time, online_history_ondev, online_history_dodev, online_history_ardev) {
    const ctx = document.getElementById("OnlineChart").getContext("2d");

    new Chart(ctx, {
        type: "bar",
        data: {
            labels: online_history_time,
            datasets: [
                {
                    label: 'Online',
                    data: online_history_ondev,
                    borderColor: "rgba(0, 166, 89)",
                    backgroundColor: "rgba(0, 166, 89, .6)"
                },
                {
                    label: 'Offline/Down',
                    data: online_history_dodev,
                    borderColor: "rgba(222, 74, 56)",
                    backgroundColor: "rgba(222, 74, 56, .6)"
                },
                {
                    label: 'Archived',
                    data: online_history_ardev,
                    borderColor: "rgba(220,220,220)",
                    backgroundColor: "rgba(220,220,220, .6)"
                }
            ]
        },
        options: {
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: "#A0A0A0"
                    }
                },
                tooltip: {
                    mode: 'index'
                }
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: {
                        color: '#A0A0A0'
                    },
                    grid: {
                        color: "rgba(0,0,0,0)"
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        color: '#A0A0A0',
                        stepSize: 1,
                        precision: 0   // ersetzt scaleIntegersOnly
                    },
                    grid: {
                        color: "rgba(0,0,0,0)"
                    }
                }
            }
        }
    });
}

function graph_online_history_icmp(online_history_time, online_history_ondev, online_history_dodev, online_history_ardev) {
    const ctx = document.getElementById("OnlineChart").getContext("2d");

    new Chart(ctx, {
        type: "bar",
        data: {
            labels: online_history_time,
            datasets: [
                {
                    label: 'Online',
                    data: online_history_ondev,
                    borderColor: "rgba(0, 166, 89)",
                    backgroundColor: "rgba(0, 166, 89, .6)"
                },
                {
                    label: 'Offline/Down',
                    data: online_history_dodev,
                    borderColor: "rgba(222, 74, 56)",
                    backgroundColor: "rgba(222, 74, 56, .6)"
                },
                {
                    label: 'Archived',
                    data: online_history_ardev,
                    borderColor: "rgba(220,220,220)",
                    backgroundColor: "rgba(220,220,220, .6)"
                }
            ]
        },
        options: {
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: "#A0A0A0"
                    }
                },
                tooltip: {
                    mode: "index"
                }
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: {
                        color: "#A0A0A0"
                    },
                    grid: {
                        color: "rgba(0,0,0,0)"
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        color: "#A0A0A0",
                        stepSize: 1,
                        precision: 0
                    },
                    grid: {
                        color: "rgba(0,0,0,0)"
                    }
                }
            }
        }
    });
}

function graph_services_history(online_history_time, online_history_down, online_history_2xx, online_history_3xx, online_history_4xx, online_history_5xx) {
    const ctx = document.getElementById("ServiceChart").getContext("2d");

    new Chart(ctx, {
        type: "bar",
        data: {
            labels: online_history_time,
            datasets: [
                {
                    label: '2xx',
                    data: online_history_2xx,
                    borderColor: "rgba(0, 166, 89)",
                    backgroundColor: "rgba(0, 166, 89, .6)"
                },
                {
                    label: '3xx',
                    data: online_history_3xx,
                    borderColor: "rgba(242,156,18)",
                    backgroundColor: "rgba(242,156,18, .7)"
                },
                {
                    label: '4xx',
                    data: online_history_4xx,
                    borderColor: "rgba(242,156,18)",
                    backgroundColor: "rgba(242,156,18, .7)"
                },
                {
                    label: '5xx',
                    data: online_history_5xx,
                    borderColor: "rgba(254,76,0)",
                    backgroundColor: "rgba(254,76,0, .7)"
                },
                {
                    label: 'Down',
                    data: online_history_down,
                    borderColor: "rgba(189, 43, 26)",
                    backgroundColor: "rgba(189, 43, 26, .7)"
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: "#A0A0A0"
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: {
                        color: "#A0A0A0"
                    },
                    grid: {
                        color: "rgba(0,0,0,0)"
                    }
                },
                y: {
                    stacked: true,
                    ticks: {
                        display: false
                    },
                    grid: {
                        color: "rgba(0,0,0,0)"
                    }
                }
            }
        }
    });
}

function graph_icmphost_history(online_history_time, online_history_down, pia_js_online_history_online) {
    const ctx = document.getElementById("ServiceChart").getContext("2d");

    new Chart(ctx, {
        type: "bar",
        data: {
            labels: online_history_time,
            datasets: [
                {
                    label: 'Online',
                    data: pia_js_online_history_online,
                    borderColor: "rgba(0, 166, 89)",
                    backgroundColor: "rgba(0, 166, 89, .6)"
                },
                {
                    label: 'Down/Offline',
                    data: online_history_down,
                    borderColor: "rgba(189, 43, 26)",
                    backgroundColor: "rgba(189, 43, 26, .7)"
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: "#A0A0A0"
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: {
                        color: "#A0A0A0"
                    },
                    grid: {
                        color: "rgba(0,0,0,0)"
                    }
                },
                y: {
                    stacked: true,
                    ticks: {
                        display: false
                    },
                    grid: {
                        color: "rgba(0,0,0,0)"
                    }
                }
            }
        }
    });
}

function graph_speedtest_history(speedtest_js_time, speedtest_js_ping, speedtest_js_down, speedtest_js_up) {
    const canvas = document.getElementById("SpeedtestChart");
    if (!canvas) return; // Abbruch, wenn Canvas nicht existiert
    const ctx = canvas.getContext("2d");

    // Vorheriges Chart zerstören, falls vorhanden
    if (window.speedtestChart instanceof Chart) {
        window.speedtestChart.destroy();
    }

    window.speedtestChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: speedtest_js_time, // Labels bleiben unverändert
            datasets: [
                {
                    label: 'Ping (ms)',
                    data: speedtest_js_ping,
                    borderColor: "rgba(22, 122, 196)",
                    backgroundColor: "rgba(22, 122, 196, 0.6)",
                    fill: false,
                    pointStyle: 'circle',
                    pointRadius: 3,
                    pointHoverRadius: 3
                },
                {
                    label: 'Download (Mbps)',
                    data: speedtest_js_down,
                    borderColor: "rgba(0, 166, 89)",
                    backgroundColor: "rgba(0, 166, 89, 0.6)",
                    fill: false,
                    pointStyle: 'circle',
                    pointRadius: 3,
                    pointHoverRadius: 3
                },
                {
                    label: 'Upload (Mbps)',
                    data: speedtest_js_up,
                    borderColor: "rgba(185, 0, 43)",
                    backgroundColor: "rgba(185, 0, 43, 0.6)",
                    fill: false,
                    pointStyle: 'circle',
                    pointRadius: 3,
                    pointHoverRadius: 3
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: { color: "#A0A0A0" }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#A0A0A0',
                        callback: function(value, index) {
                            // Einfach das Label zurückgeben, ohne zu kürzen
                            return this.getLabelForValue(index);
                        }
                    },
                    grid: {
                        color: "rgba(0, 0, 0, 0.3)"
                    }
                },
                y: {
                    ticks: {
                        beginAtZero: true,
                        display: true
                    },
                    grid: {
                        color: "rgba(0, 0, 0, 0.3)"
                    }
                }
            }
        }
    });
}
