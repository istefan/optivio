/*!
 * OptiVio — Front-end behaviour (no inline scripts; CSP script-src 'self').
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 */
( function () {
	'use strict';

	var INK = '#111827';
	var GREY = '#9ca3af';
	var LINE = '#eef0f3';

	function csrfToken() {
		var meta = document.querySelector( 'meta[name="csrf-token"]' );
		return meta ? meta.getAttribute( 'content' ) : '';
	}

	function readJson( id ) {
		var el = document.getElementById( id );
		if ( ! el ) {
			return null;
		}
		try {
			return JSON.parse( el.textContent );
		} catch ( e ) {
			return null;
		}
	}

	function formatNumber( value, decimals ) {
		return new Intl.NumberFormat( 'ro-RO', { maximumFractionDigits: decimals || 0 } ).format( value );
	}

	/* ------------------------------------------------------------------ *
	 * Generic UI helpers
	 * ------------------------------------------------------------------ */

	// Confirmation for destructive forms: <form data-confirm="Mesaj">.
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if ( form instanceof HTMLFormElement && form.dataset.confirm ) {
			if ( ! window.confirm( form.dataset.confirm ) ) {
				event.preventDefault();
			}
		}
	} );

	// Help page: open the integration guide targeted by the URL hash (#ghid-...).
	if ( window.location.hash.indexOf( '#ghid-' ) === 0 && window.bootstrap ) {
		var guide = document.getElementById( window.location.hash.slice( 1 ) );
		var panel = guide ? guide.querySelector( '.accordion-collapse' ) : null;
		if ( panel ) {
			window.bootstrap.Collapse.getOrCreateInstance( panel ).show();
			guide.scrollIntoView();
		}
	}

	// Long-running submits (AI): disable the button and show a waiting text.
	document.addEventListener( 'submit', function ( event ) {
		var button = event.target.querySelector ? event.target.querySelector( '[data-loading]' ) : null;
		if ( button && ! event.defaultPrevented ) {
			button.disabled = true;
			button.textContent = button.getAttribute( 'data-loading' );
		}
	} );

	// Toggle password visibility: <button data-toggle-password="#id">.
	document.addEventListener( 'click', function ( event ) {
		var btn = event.target.closest( '[data-toggle-password]' );
		if ( ! btn ) {
			return;
		}
		var input = document.querySelector( btn.getAttribute( 'data-toggle-password' ) );
		if ( input ) {
			input.type = 'password' === input.type ? 'text' : 'password';
			var icon = btn.querySelector( '.bi' );
			if ( icon ) {
				icon.classList.toggle( 'bi-eye' );
				icon.classList.toggle( 'bi-eye-slash' );
			}
		}
	} );

	// Live character counters: <span data-ov-counter="#field" data-min="30" data-max="60">.
	document.querySelectorAll( '[data-ov-counter]' ).forEach( function ( counter ) {
		var field = document.querySelector( counter.getAttribute( 'data-ov-counter' ) );
		if ( ! field ) {
			return;
		}
		var min = parseInt( counter.getAttribute( 'data-min' ), 10 ) || 0;
		var max = parseInt( counter.getAttribute( 'data-max' ), 10 ) || 9999;
		var update = function () {
			var n = field.value.length || ( field.getAttribute( 'placeholder' ) || '' ).length;
			counter.textContent = n + ' caractere (recomandat ' + min + '–' + max + ')';
			counter.classList.toggle( 'text-danger', n < min || n > max );
		};
		field.addEventListener( 'input', update );
		update();
	} );

	// Operator role toggles the business access matrix.
	var roleSelect = document.getElementById( 'role' );
	var grantsBox = document.getElementById( 'ov-grants' );
	if ( roleSelect && grantsBox ) {
		var syncGrants = function () {
			grantsBox.classList.toggle( 'd-none', 'operator' !== roleSelect.value );
		};
		roleSelect.addEventListener( 'change', syncGrants );
		syncGrants();
	}

	// AJAX batching: process the job queue in small slices until empty.
	var runBtn = document.getElementById( 'ov-run-batch' );
	if ( runBtn ) {
		runBtn.addEventListener( 'click', function () {
			var output = document.getElementById( 'ov-run-output' );
			var total = 0;
			var rounds = 0;
			runBtn.disabled = true;

			var tick = function () {
				rounds++;
				fetch( runBtn.dataset.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'X-CSRF-Token': csrfToken(),
						'X-Requested-With': 'XMLHttpRequest',
						Accept: 'application/json'
					}
				} )
					.then( function ( r ) {
						return r.json();
					} )
					.then( function ( data ) {
						total += data.processed || 0;
						output.textContent = 'Rundă ' + rounds + ': procesate ' + total + ' joburi, în așteptare ' + data.pending + ( 'locked' === data.status ? ' (alt proces rulează deja)' : '' );
						if ( data.pending > 0 && data.processed > 0 && rounds < 30 ) {
							window.setTimeout( tick, 400 );
						} else {
							runBtn.disabled = false;
						}
					} )
					.catch( function () {
						output.textContent = 'Eroare la rularea cozii.';
						runBtn.disabled = false;
					} );
			};
			tick();
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Dashboard charts
	 * ------------------------------------------------------------------ */

	/* ------------------------------------------------------------------ *
	 * Calls by hour (stacked bars)
	 * ------------------------------------------------------------------ */

	var callsData = readJson( 'ov-calls-data' );
	var hoursCanvas = document.getElementById( 'ov-chart-hours' );
	if ( callsData && hoursCanvas && 'undefined' !== typeof window.Chart ) {
		var pick = function ( key ) {
			return callsData.hours.map( function ( h ) {
				return h[ key ];
			} );
		};
		new window.Chart( hoursCanvas, {
			type: 'bar',
			data: {
				labels: callsData.hours.map( function ( h ) {
					return ( h.hour < 10 ? '0' : '' ) + h.hour;
				} ),
				datasets: [
					{ label: 'Acceptate', data: pick( 'accepted' ), backgroundColor: '#111827', borderRadius: 4, maxBarThickness: 22 },
					{ label: 'Refuzate', data: pick( 'rejected' ), backgroundColor: '#ef4444', borderRadius: 4, maxBarThickness: 22 },
					{ label: 'Pierdute', data: pick( 'missed' ), backgroundColor: '#d1d5db', borderRadius: 4, maxBarThickness: 22 }
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
					tooltip: { backgroundColor: '#111827', padding: 10, cornerRadius: 8 }
				},
				scales: {
					x: { stacked: true, grid: { display: false }, border: { display: false } },
					y: { stacked: true, beginAtZero: true, grid: { color: '#eef0f3' }, border: { display: false }, ticks: { precision: 0 } }
				}
			}
		} );
	}

	var data = readJson( 'ov-dashboard-data' );
	if ( ! data || 'undefined' === typeof window.Chart ) {
		return;
	}

	var Chart = window.Chart;
	Chart.defaults.font.family = getComputedStyle( document.body ).fontFamily;
	Chart.defaults.font.size = 11;
	Chart.defaults.color = GREY;

	var tooltip = {
		backgroundColor: INK,
		titleColor: '#fff',
		bodyColor: '#e5e7eb',
		padding: 10,
		cornerRadius: 8,
		displayColors: false,
		titleFont: { weight: '600' }
	};

	function metricSuffix( metric ) {
		return 'revenue' === metric ? ' ' + data.currency : '';
	}

	// Main line chart.
	var mainCanvas = document.getElementById( 'ov-chart-main' );
	var mainChart = null;
	if ( mainCanvas ) {
		var ctx = mainCanvas.getContext( '2d' );
		var gradient = ctx.createLinearGradient( 0, 0, 0, 280 );
		gradient.addColorStop( 0, 'rgba(17, 24, 39, 0.16)' );
		gradient.addColorStop( 1, 'rgba(17, 24, 39, 0)' );

		mainChart = new Chart( ctx, {
			type: 'line',
			data: {
				labels: data.series.labels,
				datasets: [
					{
						label: 'Perioada curentă',
						data: data.series.current,
						borderColor: INK,
						backgroundColor: gradient,
						fill: true,
						tension: 0.42,
						borderWidth: 2.2,
						pointRadius: 0,
						pointHoverRadius: 5,
						pointHoverBackgroundColor: INK,
						pointHoverBorderColor: '#fff',
						pointHoverBorderWidth: 2
					},
					{
						label: 'Perioada anterioară',
						data: data.series.previous,
						borderColor: '#cbd5e1',
						borderDash: [ 4, 4 ],
						fill: false,
						tension: 0.42,
						borderWidth: 1.6,
						pointRadius: 0,
						pointHoverRadius: 3
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { display: false },
					tooltip: Object.assign( {}, tooltip, {
						displayColors: true,
						callbacks: {
							label: function ( item ) {
								return ' ' + item.dataset.label + ': ' + formatNumber( item.parsed.y, 2 ) + metricSuffix( data.series.metric );
							}
						}
					} )
				},
				scales: {
					x: { grid: { display: false }, border: { display: false }, ticks: { maxTicksLimit: 10 } },
					y: { beginAtZero: true, grid: { color: LINE }, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 6 } }
				}
			}
		} );
	}

	// Weekday bars.
	var barsCanvas = document.getElementById( 'ov-chart-bars' );
	var barsChart = null;
	function barColors( values ) {
		var max = Math.max.apply( null, values );
		return values.map( function ( v ) {
			return v === max && max > 0 ? INK : '#d1d5db';
		} );
	}
	if ( barsCanvas ) {
		barsChart = new Chart( barsCanvas, {
			type: 'bar',
			data: {
				labels: [ 'Lu', 'Ma', 'Mi', 'Jo', 'Vi', 'Sâ', 'Du' ],
				datasets: [ {
					data: data.weekdays,
					backgroundColor: barColors( data.weekdays ),
					borderRadius: 6,
					borderSkipped: false,
					maxBarThickness: 18
				} ]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: Object.assign( {}, tooltip, {
						callbacks: {
							label: function ( item ) {
								return formatNumber( item.parsed.y ) + ' conversii';
							}
						}
					} )
				},
				scales: {
					x: { grid: { display: false }, border: { display: false } },
					y: { beginAtZero: true, grid: { color: LINE }, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 4 } }
				}
			}
		} );
	}

	// Readiness donut.
	var donutCanvas = document.getElementById( 'ov-chart-donut' );
	if ( donutCanvas ) {
		var score = Math.max( 0, Math.min( 100, data.readiness || 0 ) );
		new Chart( donutCanvas, {
			type: 'doughnut',
			data: {
				labels: [ 'Configurat', 'De completat' ],
				datasets: [ {
					data: [ score, 100 - score ],
					backgroundColor: [ INK, '#e5e7eb' ],
					borderWidth: 0,
					borderRadius: score > 0 && score < 100 ? 8 : 0,
					spacing: score > 0 && score < 100 ? 2 : 0
				} ]
			},
			options: {
				cutout: '78%',
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: Object.assign( {}, tooltip, {
						callbacks: {
							label: function ( item ) {
								return item.label + ': ' + item.parsed + '%';
							}
						}
					} )
				}
			}
		} );
	}

	// Filters (AJAX — scope is enforced server side).
	var metricSel = document.getElementById( 'ov-chart-metric' );
	var daysSel = document.getElementById( 'ov-chart-days' );
	function reload() {
		var url = new URL( data.endpoint, window.location.href );
		url.searchParams.set( 'metric', metricSel.value );
		url.searchParams.set( 'days', daysSel.value );

		fetch( url.toString(), {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
		} )
			.then( function ( r ) {
				if ( ! r.ok ) {
					throw new Error( 'HTTP ' + r.status );
				}
				return r.json();
			} )
			.then( function ( payload ) {
				data.series = payload.series;
				if ( mainChart ) {
					mainChart.data.labels = payload.series.labels;
					mainChart.data.datasets[ 0 ].data = payload.series.current;
					mainChart.data.datasets[ 1 ].data = payload.series.previous;
					mainChart.update();
				}
				if ( barsChart ) {
					barsChart.data.datasets[ 0 ].data = payload.weekdays;
					barsChart.data.datasets[ 0 ].backgroundColor = barColors( payload.weekdays );
					barsChart.update();
				}
				var total = document.getElementById( 'ov-series-total' );
				if ( total ) {
					total.textContent = formatNumber( payload.series.total, 'revenue' === payload.series.metric ? 2 : 0 ) + metricSuffix( payload.series.metric );
				}
			} )
			.catch( function () {} );
	}
	if ( metricSel && daysSel ) {
		metricSel.addEventListener( 'change', reload );
		daysSel.addEventListener( 'change', reload );
	}
}() );
