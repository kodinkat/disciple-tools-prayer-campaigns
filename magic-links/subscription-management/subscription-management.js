let calendar_subscribe_object = window.jsObject
let current_time_zone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Chicago'
if ( calendar_subscribe_object.timezone ){
  current_time_zone = calendar_subscribe_object.timezone
}
let escaped_translations = window.SHAREDFUNCTIONS.escapeObject(calendar_subscribe_object.translations)
const number_of_days = ( calendar_subscribe_object.end_timestamp - calendar_subscribe_object.start_timestamp ) / day_in_seconds

let verified = false

function toggle_danger() {
  $('.danger-zone-content').toggleClass('collapsed');
  $('.chevron').toggleClass('toggle_up');
}

jQuery(document).ready(function($){

  //set up array of days and time slots according to timezone
  let days = window.campaign_scripts.calculate_day_times(current_time_zone);


  let week_day_names = window.campaign_scripts.get_days_of_the_week_initials(navigator.language, 'narrow')
  let headers = `
    <div class="new_weekday">${week_day_names[0]}</div>
    <div class="new_weekday">${week_day_names[1]}</div>
    <div class="new_weekday">${week_day_names[2]}</div>
    <div class="new_weekday">${week_day_names[3]}</div>
    <div class="new_weekday">${week_day_names[4]}</div>
    <div class="new_weekday">${week_day_names[5]}</div>
    <div class="new_weekday">${week_day_names[6]}</div>
  `
  let daily_time_select = $('#cp-daily-time-select')
  let modal_calendar = $('#day-select-calendar')
  let now = new Date().getTime()/1000
  let selected_times = [];
  calendar_subscribe_object.my_recurring = {}

  /**
   * Add notice showing that my times have been verified
   */
  if ( verified ){
    $("#times-verified-notice").show()
  }


  update_timezone()
  draw_calendar()
  display_missing_time_slots()

  calculate_my_time_slot_coverage()

  setup_duration_options()

  setup_daily_prayer_times()
  setup_individual_prayer_times()


  //change timezone
  $('#confirm-timezone').on('click', function (){
    current_time_zone = $("#timezone-select").val()
    update_timezone()
    days = window.campaign_scripts.calculate_day_times(current_time_zone)
    draw_calendar()
    display_my_commitments()
    draw_modal_calendar()
    display_missing_time_slots()
  })
  /**
   * Remove a prayer time
   */
  let time_to_delete = null
  let id_to_delete = null
  $(document).on("click", '.remove-my-prayer-time', function (){
    let x = $(this)
    id_to_delete = x.data("report")
    time_to_delete = x.data('time')

    $('#delete-time-modal').foundation('open');

    let label = window.campaign_scripts.timestamp_to_format( time_to_delete, { year: "numeric", month: "long", day: "numeric", hour:"numeric", minute: "numeric" })
    console.log(label);
    $('#delete-time-modal-text').html(`${label}?`)

    if ( time_to_delete < now + day_in_seconds *2 ){
      $('#delete-time-extra-warning').show()
    }

  })


  $('#confirm-delete-my-time-modal').on('click', function (){

    $(this).addClass("loading");
    jQuery.ajax({
      type: "POST",
      data: JSON.stringify({ action: 'delete', parts: calendar_subscribe_object.parts, report_id: id_to_delete }),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      url: calendar_subscribe_object.root + calendar_subscribe_object.parts.root + '/v1/' + calendar_subscribe_object.parts.type,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', calendar_subscribe_object.nonce )
      }
    })
    .done(function(data){
      $($(`*[data-report=${id_to_delete}]`)[0].parentElement.parentElement).css({'background-color':'lightgray','text-decoration':'line-through'});
      $($(`*[data-report=${id_to_delete}]`)[1].parentElement.parentElement).css({'background-color':'lightgray','text-decoration':'line-through'});
      $('#confirm-delete-my-time-modal').removeClass("loading");
      $(`#selected-${time_to_delete}`).addClass('deleted-time')
      $('#delete-time-modal').foundation('close');
    })
    .fail(function(e) {
      console.log(e)
      jQuery('#error').html(e)
    })
  })

  /**
   * Modal for displaying on individual day
   */
  $('.new-day-number').on( 'click', function (){
    let day_timestamp = $(this).data('day')
    draw_day_coverage_content_modal( day_timestamp );
  })


  function calculate_my_time_slot_coverage(){
    let html = ``
    for ( const time in calendar_subscribe_object.my_recurring ){
      if ( calendar_subscribe_object.my_recurring[time].count > 1 ){
        let last_report_id = calendar_subscribe_object.my_recurring[time].report_ids[calendar_subscribe_object.my_recurring[time].report_ids.length-1]
        let last_report = calendar_subscribe_object.my_commitments.filter(x=>x.report_id === last_report_id )[0]
        let last_report_time = parseInt(last_report.time_begin)
        html += `<tr>
          <td>${time}</td>
          <td>${calendar_subscribe_object.my_recurring[time].count}</td>
          <td><button class="button change-time-bulk" data-key="${time}">${escaped_translations.change_daily_time}</button></td>
          <td><button class="button outline delete-time-bulk" data-key="${time}">x</button></td>
          <td><button class="button outline extend-time-bulk" data-key="${time}" ${last_report_time >= (now+21*day_in_seconds) ? "disabled":""}>${escaped_translations.extend_3_months}</button></td>
          </tr>
        `
      }
    }
    $('#recurring_time_slots').empty().html(html)
  }

  let opened_daily_time_changed_modal = null
  $(document).on('click', '.change-time-bulk', function (){
    opened_daily_time_changed_modal = $(this).data('key');
    $('#change-times-modal').foundation('open')
  })
  $('#update-daily-time').on('click', function (){
    $(this).addClass('loading')
    const time = parseInt($('#change-time-select').val())
    const new_time = window.campaign_scripts.day_start(calendar_subscribe_object.my_recurring[opened_daily_time_changed_modal].time, current_time_zone) + time
    let data = {
      action: 'change_times',
      offset: new_time - calendar_subscribe_object.my_recurring[opened_daily_time_changed_modal].time,
      report_ids: calendar_subscribe_object.my_recurring[opened_daily_time_changed_modal].report_ids,
      parts: calendar_subscribe_object.parts
    }
    jQuery.ajax({
      type: "POST",
      data: JSON.stringify(data),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      url: calendar_subscribe_object.root + calendar_subscribe_object.parts.root + '/v1/' + calendar_subscribe_object.parts.type,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', calendar_subscribe_object.nonce )
      }
    }).then(data=>{
      calendar_subscribe_object.my_commitments = data;
      draw_calendar();
      calculate_my_time_slot_coverage()
      $(this).removeClass('loading')
      $('#change-times-modal').foundation('close')
    })
  })

  let opened_delete_time_modal = null
  $(document).on('click', '.delete-time-bulk', function (){
    opened_delete_time_modal = $(this).data('key');
    $('#delete-time-slot-text').text(opened_delete_time_modal)
    $('#delete-times-modal').foundation('open')
  })
  $('#confirm-delete-daily-time').on('click', function (){
    $(this).addClass('loading')
    let data = {
      action: 'delete_times',
      report_ids: calendar_subscribe_object.my_recurring[opened_delete_time_modal].report_ids,
      parts: calendar_subscribe_object.parts
    }
    jQuery.ajax({
      type: "POST",
      data: JSON.stringify(data),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      url: calendar_subscribe_object.root + calendar_subscribe_object.parts.root + '/v1/' + calendar_subscribe_object.parts.type,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', calendar_subscribe_object.nonce )
      }
    }).then(data=>{
      calendar_subscribe_object.my_commitments = data;
      draw_calendar();
      calculate_my_time_slot_coverage()
      $(this).removeClass('loading')
      $('#delete-times-modal').foundation('close')
    })
  })

  let opened_extend_time_modal = null;
  $(document).on('click', '.extend-time-bulk', function (){
    opened_extend_time_modal = $(this).data('key');
    let last_report_id = calendar_subscribe_object.my_recurring[opened_extend_time_modal].report_ids[calendar_subscribe_object.my_recurring[opened_extend_time_modal].report_ids.length-1]
    let last_report = calendar_subscribe_object.my_commitments.filter(x=>x.report_id === last_report_id )[0]
    let last_report_time = parseInt(last_report.time_begin)

    let in_three_months_in_seconds = last_report_time + day_in_seconds * 90;
    let label = window.campaign_scripts.timestamp_to_format( in_three_months_in_seconds, { year:"numeric", month: "long", day: "numeric" }, current_time_zone )
    $('#extend-time-slot-text').text(label)
    $('#extend-times-modal').foundation('open')
  })
  $('#confirm-extend-daily-time').on('click', function (){
    //add 3 months after latest time
    let recurring = calendar_subscribe_object.my_recurring[opened_extend_time_modal]
    let last_report_id = recurring.report_ids[recurring.report_ids.length-1]
    let last_report = calendar_subscribe_object.my_commitments.filter(x=>x.report_id === last_report_id )[0]
    let last_report_time = parseInt(last_report.time_begin)

    let duration = recurring.duration

    let start_time = last_report_time + day_in_seconds
    let start_date = window.luxon.DateTime.fromSeconds(start_time).setZone(current_time_zone)

    selected_times = [];
    for ( let i = 0; i < 90; i++){
      let time_date = start_date.plus({day:i})
      let time = parseInt( time_date.toFormat('X') );
      let time_label = time_date.toFormat('MMMM dd HH:mm a');
      let already_added = selected_times.find(k=>k.time===time)
      if ( !already_added && time > last_report_time && time >= calendar_subscribe_object['start_timestamp'] ) {
        selected_times.push({time: time, duration: duration, label: time_label})
      }
    }
    submit_times().then(a=>{
      $('#extend-times-modal').foundation('close')
      $(`.extend-time-bulk[data-key="${opened_extend_time_modal}"]`).prop( "disabled", true );
    })
  })

  function update_timezone(){
    $('.timezone-current').html(current_time_zone)
    $('#selected-time-zone').val(current_time_zone).text(current_time_zone)
  }
  /**
   * Draw or refresh the main calendar
   */
  function draw_calendar( id = 'calendar-content'){
    let now = new Date().getTime()/1000
    let content = $(`#${id}`);
    content.empty();

    let current_month = window.campaign_scripts.timestamp_to_format( now, { month:"long" }, current_time_zone);
    let months = {};
    days.forEach(day=> {
      if (day.month === current_month || day.key > now) {
        if (!months[day.month]) {
          months[day.month] = {key: day.key}
        }
      }
    })
    let calendar = ``
    Object.keys(months).forEach( (key, index) =>{
      let this_month_content = ``;
      let day_number = window.campaign_scripts.get_day_number(months[key].key, current_time_zone);
      //add extra days at the month start
      for (let i = 0; i < day_number; i++) {
        this_month_content += `<div class="new_day_cell"></div>`
      }
      // fill in calendar
      days.filter(k=>k.month===key && k.key < months[key].key+35*day_in_seconds ).forEach(day=>{
        this_month_content +=`
          <div class="new_day_cell">
            <div class="new-day-number" data-time="${window.lodash.escape(day.day_start_zoned)}" data-day="${window.lodash.escape(day.day_start_zoned)}">${window.lodash.escape(day.day)}
              <div><small>${window.lodash.escape(parseInt(day.percent))}%</small></div>
              <div class="progress-bar-container">
                  <div class="progress-bar" data-percent="${window.lodash.escape(day.percent)}" style="width:${window.lodash.escape(parseInt(day.percent))}%"></div>
              </div>
            </div>
            <div class="day-extra" id=calendar-extra-${window.lodash.escape(day.day_start_zoned)}></div>
        </div>
        `
      })
      //add extra days at the month end
      if (day_number!==0) {
        for (let i = 1; i <= 7 - day_number; i++) {
          this_month_content += `<div class="new_day_cell"></div>`
        }
      }
      let display_calendar = index === 0 ? 'display:block' : 'display:none';
      let next_month_button = index < Object.keys(months).length -1 ? '' : 'display:none'
      let prev_month_button = index > 0 ? '' : 'display:none'
      calendar += `
        <div class="calendar-month" data-month-index="${index}" style="${display_calendar}">
          <div style="display: flex">
            <div class="goto-month-container"><button class="cp-goto-month" data-month-target="${index-1}" style="${prev_month_button}"><</button></div>
            <div style="flex-grow:1;">
              <div class="calendar-title">
                <h2>${window.lodash.escape(key)} ${new Date(months[key].key * 1000).getFullYear()}</h2>
              </div>
              <div class="new_calendar">
                ${headers}
                ${this_month_content}
              </div>
            </div>
            <div class="goto-month-container"><button class="cp-goto-month" data-month-target="${index+1}" style="${next_month_button}">></button></div>
          </div>
        </div>
      `
    })


    content.html(`<div class="grid-x" id="selection-grid-wrapper">${calendar}</div>`)
    display_my_commitments()
  }
  $(document).on('click', '#calendar-content .cp-goto-month', function (){
    let target = $(this).data('month-target');
    $('#calendar-content .calendar-month').hide()
    $(`#calendar-content .calendar-month[data-month-index='${target}']`).show()
  })

  function display_missing_time_slots(){
    let ordered_missing = [];
    Object.keys(window.campaign_scripts.missing_slots).forEach(k=>{
      ordered_missing.push({'label':k, slots:window.campaign_scripts.missing_slots[k]})
    })

    ordered_missing.sort((a,b)=>a.slots.length-b.slots.length)
    if ( ordered_missing.length > 0 ){
      $('#cp-missing-times-container').show()
    }

    let content = ``;
    let index = 0;
    ordered_missing.forEach(m=>{
      index++;
      content += `<div class="missing-time-slot" style="${index>5?'display:none':''}"><strong>${m.label}:</strong>&nbsp;`
      if ( m.slots.length < 5 ){
        content += m.slots.slice(0, 5).map(a=>{return window.campaign_scripts.timestamp_to_month_day(a)}).join(', ')
      } else {
        content += calendar_subscribe_object.translations.on_x_days.replace('%s', m.slots.length)
      }
      // content += `.<button class="cp-select-missing-time clear-button" value="${m.label}" style="padding:5px">${calendar_subscribe_object.translations.pray_this_time}</button>`
      content += `</div>`
    })
    if( ordered_missing.length >= 5 ){
      content += `<div class="missing-time-slot">
          <button class="button" id="cp-show-more-missing">
            <strong>${calendar_subscribe_object.translations.and_x_more.replace('%s', ordered_missing.length - 5)}</strong>
          </button>
        </div>`
    }

    $('#cp-missing-time-slots').html(content)
  }
  $(document).on('click', '#cp-show-more-missing', function (){
    $('.missing-time-slot').show();
    $('#cp-show-more-missing').hide();
  })

  $(document).on('click', '.cp-select-missing-time', function (){
    let label = $(this).val();
    let times = window.campaign_scripts.missing_slots[label];
    times.forEach(time=>{
      let time_label = window.campaign_scripts.timestamp_to_format( time, { month: "long", day: "numeric", hour:"numeric", minute: "numeric" }, current_time_zone)
      let already_added = selected_times.find(k=>k.time===time)
      if ( !already_added && time > now && time >= calendar_subscribe_object['start_timestamp'] && time < calendar_subscribe_object['end_timestamp'] ){
        selected_times.push({time: time, duration: calendar_subscribe_object.slot_length, label: time_label})
      }
    })
    display_selected_times();

    $('.cp-view').hide()
    let view_to_open = 'cp-view-confirm'
    $(`#${view_to_open}`).show()
    let elmnt = document.getElementById("cp-wrapper");
    elmnt.scrollIntoView();
  })

  /**
   * Show my commitment under each day
   */
  function display_my_commitments(){
    $('.day-extra').empty()
    calendar_subscribe_object.my_recurring = {}
    calendar_subscribe_object.my_commitments.forEach(c=>{
      let time = c.time_begin;
      let now = new Date().getTime()/1000
      if ( time >= now ){
        let day_timestamp = 0
        days.forEach(d=>{
          if ( d.day_start_zoned < c.time_begin ){
            day_timestamp = d.day_start_zoned
          }
        })

        let date = new Date( time * 1000 );
        let weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        let day_number = date.getDate();
        let day_weekday = weekdays[ date.getDay() ];

        let summary_text = window.campaign_scripts.timestamps_to_summary(c.time_begin, c.time_end, current_time_zone)
        if ( !calendar_subscribe_object.my_recurring[summary_text] ){
          calendar_subscribe_object.my_recurring[summary_text] = { count: 0, report_ids: [], time:parseInt(c.time_begin), duration: (parseInt(c.time_end) - parseInt(c.time_begin))/60 }
        }
        calendar_subscribe_object.my_recurring[summary_text].count++;
        calendar_subscribe_object.my_recurring[summary_text].report_ids.push(c.report_id)

        $(`#calendar-extra-${window.lodash.escape(day_timestamp)}`).append(`
            <div class="prayer-commitment" id="selected-${window.lodash.escape(time)}"
                data-time="${window.lodash.escape(time)}">
                <div class="prayer-commitment-text">
                    ${window.lodash.escape(summary_text)}
                    <i class="fi-x remove-selection remove-my-prayer-time" data-report="${window.lodash.escape(c.report_id)}" data-time="${window.lodash.escape(time)}" data-day="${window.lodash.escape(day_timestamp)}"></i>
                </div>
            </div>
        `)
        $('#mobile-commitments-container').append(`
          <div class="mobile-commitments" id="mobile-commitment-${window.lodash.escape(time)}">
              <div class="mobile-commitments-date">
                  <div class="mc-day"><b>${window.lodash.escape(day_weekday)}</b></div>
                  <div class="mc-day">${window.lodash.escape(day_number)}</div>
              </div>
              <div class="mc-prayer-commitment-description">
                  <div class="mc-prayer-commitment-text">
                      <div class="mc-description-duration">${window.lodash.escape(summary_text)}</div>
                      <div class="mc-description-time"> <i class="fi-x remove-selection remove-my-prayer-time" style="margin-left:6px;" data-report="${window.lodash.escape(c.report_id)}" data-time="${window.lodash.escape(time)}" data-day="${window.lodash.escape(day_timestamp)}"></i></div>
                  </div>
              </div>
          </div>`)
      }
    })
  }


  /**
   * Modal for displaying on individual day
   */
  function draw_day_coverage_content_modal( day_timestamp ){
    $('#view-times-modal').foundation('open')
    let list_title = jQuery('#list-modal-title')
    let day=days.find(k=>k.day_start_zoned===day_timestamp)
    list_title.empty().html(`<h2 class="section_title">${window.lodash.escape(day.formatted)}</h2>`)
    let day_times_content = $('#day-times-table-body')
    let times_html = ``
    let row_index = 0
    console.log(day);
    day.slots.forEach(slot=>{
      let background_color = 'white'
      if ( slot.key < calendar_subscribe_object.start_timestamp ){
        background_color = 'rgba(0,0,0,0.44)'
      }
      if ( slot.subscribers > 0) {
        background_color = '#1e90ff75'
      }
      if ( row_index === 0 ){
        times_html += `<tr><td>${window.lodash.escape(slot.formatted)}</td>`
      }
      times_html +=`<td style="background-color:${background_color}">
          ${window.lodash.escape(slot.subscribers)} <i class="fi-torsos"></i>
      </td>`
      if ( times_html === 3 ){
        times_html += `</tr>`
      }
      row_index = row_index === 3 ? 0 : row_index + 1;
    })
    day_times_content.empty().html(`<div class="grid-x"> ${times_html} </div>`)
  }


  /**
   * daily prayer time screen
   */
  function setup_daily_select(){
    let daily_time_select = $('.cp-daily-time-select')

    let select_html = `<option value="false">${escaped_translations.select_a_time}</option>`

    let time_index = 0;
    let start_of_today = new Date('2023-01-01')
    start_of_today.setHours(0,0,0,0)
    let start_time_stamp = start_of_today.getTime()/1000
    while ( time_index < day_in_seconds ){
      let time_formatted = window.campaign_scripts.timestamp_to_time(start_time_stamp+time_index)
      let text = ''
      let fully_covered = window.campaign_scripts.time_slot_coverage[time_formatted] ? window.campaign_scripts.time_slot_coverage[time_formatted].length === window.campaign_scripts.time_label_counts[time_formatted] : false;
      let level_covered =  window.campaign_scripts.time_slot_coverage[time_formatted] ? Math.min(...window.campaign_scripts.time_slot_coverage[time_formatted]) : 0
      if ( fully_covered && level_covered > 1  ){
        text = `(${escaped_translations.fully_covered_x_times.replace( '%1$s', level_covered)})`
      } else if ( fully_covered ) {
        text = `(${escaped_translations.fully_covered_once})`
      } else if ( window.campaign_scripts.time_slot_coverage[time_formatted] ){
        text = `${escaped_translations.percent_covered.replace('%s', (window.campaign_scripts.time_slot_coverage[time_formatted].length / number_of_days * 100).toFixed(1) + '%')}`
      }
      select_html += `<option value="${window.lodash.escape(time_index)}">
          ${window.lodash.escape(time_formatted)} ${ window.lodash.escape(text) }
        </option>`
      time_index += calendar_subscribe_object.slot_length * 60
    }
    daily_time_select.empty();
    daily_time_select.html(select_html)

  }

  function setup_duration_options(){
    let duration_options_html = ``
    for (const prop in calendar_subscribe_object.duration_options) {
      if (calendar_subscribe_object.duration_options.hasOwnProperty(prop) && parseInt(prop) >= parseInt(calendar_subscribe_object.slot_length) ) {
        duration_options_html += `<option value="${window.lodash.escape(prop)}">${window.lodash.escape(calendar_subscribe_object.duration_options[prop].label)}</option>`
      }
    }
    $(".cp-time-duration-select").html(duration_options_html)

  }


  function setup_daily_prayer_times(){
    setup_daily_select()

    daily_time_select.on("change", function (){
      $('#cp-confirm-daily-times').attr('disabled', false)
    })


    $('#cp-confirm-daily-times').on("click", function (){
      let daily_time_selected = parseInt($("#cp-daily-time-select").val());
      let duration = parseInt($("#cp-prayer-time-duration-select").val())

      let start_time = days[0].key + daily_time_selected;
      let start_date = window.luxon.DateTime.fromSeconds(start_time).setZone(current_time_zone)
      let now = new Date().getTime()/1000
      for ( let i = 0; i < days.length; i++){
        let time_date = start_date.plus({day:i})
        let time = parseInt( time_date.toFormat('X') );
        let time_label = time_date.toFormat('MMMM dd HH:mm a');
        let already_added = selected_times.find(k=>k.time===time)
        if ( !already_added && time > now && time >= calendar_subscribe_object['start_timestamp'] && time < calendar_subscribe_object['end_timestamp'] ) {
          selected_times.push({time: time, duration: duration, label: time_label})
        }
      }
      submit_times();
    })
  }

  /**
   * Individual prayer times screen
   */
  function setup_individual_prayer_times(){
    draw_modal_calendar()

    let current_time_selected = $("cp-individual-time-select").val();
    $(document).on( 'click', '.remove-prayer-time-button', function (){
      let time = parseInt($(this).data('time'))
      selected_times = selected_times.filter(t=>parseInt(t.time) !== time)
      display_selected_times()
    })
    //add a selected time to the array
    $('#cp-add-prayer-time').on("click", function(){
      current_time_selected = $("#cp-individual-time-select").val();
      let duration = parseInt($("#cp-individual-prayer-time-duration-select").val())
      let time_label = window.campaign_scripts.timestamp_to_format( current_time_selected, { month: "long", day: "numeric", hour:"numeric", minute: "numeric" }, current_time_zone)
      let now = new Date().getTime()/1000
      let already_added = selected_times.find(k=>k.time===current_time_selected)
      if ( !already_added && current_time_selected > now && current_time_selected >= calendar_subscribe_object['start_timestamp'] ){
        $('#cp-time-added').show().fadeOut(1000)
        selected_times.push({time: current_time_selected, duration: duration, label: time_label })
      }
      display_selected_times()
      $('#cp-confirm-individual-times').attr('disabled', false)
    })

    $(document).on('click', '#day-select-calendar .cp-goto-month', function (){
      let target = $(this).data('month-target');
      $('#day-select-calendar .calendar-month').hide()
      $(`#day-select-calendar .calendar-month[data-month-index='${target}']`).show()
    })

    //when a day is clicked on from the calendar
    $(document).on('click', '.day-in-select-calendar', function (){
      $('#day-select-calendar div').removeClass('selected-day')
      $(this).toggleClass('selected-day')
      //get day and build content
      let day_key = parseInt($(this).data("day"))
      let day=days.find(k=>k.key===day_key);
      //set time key on add button
      $('#cp-add-prayer-time').data("day", day_key).attr('disabled', false)

      //build time select
      let select_html = ``;
      day.slots.forEach(slot=> {
        let text = ``
        if ( slot.subscribers===1 ) {
          text = "(covered once)";
        }
        if ( slot.subscribers > 1 ) {
          text = `(covered ${slot.subscribers} times)`;
        }
        let disabled = slot.key < calendar_subscribe_object.start_timestamp ? 'disabled' : '';
        let selected = ( slot.key % day_in_seconds) === ( current_time_selected % day_in_seconds ) ? "selected" : '';
        select_html += `<option value="${window.lodash.escape(slot.key)}" ${selected} ${disabled}>
          ${window.lodash.escape(slot.formatted)} ${window.lodash.escape(text)}
      </option>`
      })
      $('#cp-individual-time-select').html(select_html).attr('disabled', false)
    })


    $('#cp-confirm-individual-times').on( 'click', function (){
      submit_times();
    })
  }




  //build the list of individually selected times
  function display_selected_times(){
    let html = ""
    selected_times.sort((a,b)=>{
      return a.time - b.time
    });
    selected_times.forEach(time=>{
      html += `<li>
          ${escaped_translations.time_slot_label.replace( '%1$s', time.label).replace( '%2$s', time.duration )}
          <button class="remove-prayer-time-button" data-time="${time.time}">x</button>
      </li>`

    })
    $('.cp-display-selected-times').html(html)
  }

  //dawn calendar in date select view

  function draw_modal_calendar() {
    let current_month = window.campaign_scripts.timestamp_to_format( now, { month:"long" }, current_time_zone);
    modal_calendar.empty()
    let list = ''
    let months = {};
    days.forEach(day=> {
      if (day.month === current_month || day.key > now) {
        if (!months[day.month]) {
          months[day.month] = {key: day.key}
        }
      }
    })
    Object.keys(months).forEach( (key, index) =>{

      let this_month_content = ``
      let day_number = window.campaign_scripts.get_day_number(months[key].key, current_time_zone);

      //add extra days at the month start
      for (let i = 0; i < day_number; i++) {
        this_month_content += `<div class="day-cell disabled-calendar-day"></div>`
      }
      // fill in calendar
      days.filter(k=>{ return k.month === key && k.key < months[key].key + 35*day_in_seconds }).forEach(day=>{
        let disabled = (day.key + day_in_seconds) < now;
        this_month_content += `
          <div class="day-cell ${disabled ? 'disabled-calendar-day':'day-in-select-calendar'}" data-day="${window.lodash.escape(day.key)}">
              ${window.lodash.escape(day.day)}
          </div>
        `
      })
      //add extra days at the month end
      if (day_number!==0) {
        for (let i = 1; i <= 7 - day_number; i++) {
          this_month_content += `<div class="day-cell disabled-calendar-day"></div>`
        }
      }

      let display_calendar = index === 0 ? 'display:block' : 'display:none';
      let next_month_button = index < Object.keys(months).length -1 ? '' : 'display:none'
      let prev_month_button = index > 0 ? '' : 'display:none'

      list += `<div class="calendar-month" data-month-index="${index}" style="${display_calendar}">
        <div style="display: flex; justify-content: center">
          <div class="goto-month-container"><button class="cp-goto-month" data-month-target="${index-1}" style="${prev_month_button}"><</button></div>
          <div>
            <h3 class="month-title center">
                <strong>${window.lodash.escape(key).substring(0,3)}</strong>
                ${new Date(months[key].key * 1000).getFullYear()}
            </h3>
            <div class="calendar">
              ${headers}
              ${this_month_content}
            </div>
          </div>
          <div class="goto-month-container"><button class="cp-goto-month" data-month-target="${index+1}" style="${next_month_button}">></button></div>
        </div>
        </div></div>
      `
    })
    modal_calendar.html(list)
  }




  let submit_times = function(){
    let submit_button = $('.submit-form-button')
    submit_button.addClass( 'loading' )
    let data = {
      action: 'add',
      selected_times,
      parts: calendar_subscribe_object.parts
    }
    return jQuery.ajax({
      type: "POST",
      data: JSON.stringify(data),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      url: calendar_subscribe_object.root + calendar_subscribe_object.parts.root + '/v1/' + calendar_subscribe_object.parts.type,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', calendar_subscribe_object.nonce )
      }
    })
    .done(function(response){
      $('.hide-on-success').hide();
      submit_button.removeClass('loading')
      $('#modal-calendar').hide()

      $(`.success-confirmation-section`).show()
      calendar_subscribe_object.my_commitments = response
      display_my_commitments()
      submit_button.prop('disabled', false)
    })
    .fail(function(e) {
      console.log(e)
      $('#selection-error').empty().html(`<div class="cell center">
                        So sorry. Something went wrong. Please, contact us to help you through it, or just try again.<br>
                        <a href="${window.lodash.escape(window.location.href)}">Try Again</a>
                        </div>`).show()
      $('#error').html(e)
      submit_button.removeClass('loading')
    })
  }

  $('.close-ok-success').on("click", function (){
    window.location.reload()
  })


  $('#allow_notifications').on('change', function (){
    let selected_option = $(this).val();
    $('.notifications_allowed_spinner').addClass('active')
    jQuery.ajax({
      type: "POST",
      data: JSON.stringify({parts: calendar_subscribe_object.parts, allowed:selected_option==="allowed"}),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      url: calendar_subscribe_object.root + calendar_subscribe_object.parts.root + '/v1/' + calendar_subscribe_object.parts.type + '/allow-notifications',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', calendar_subscribe_object.nonce )
      }
    }).done(function(){
      $('.notifications_allowed_spinner').removeClass('active')
    })
    .fail(function(e) {

    })
  })

  /**
   * Delete profile
   */
  $('#confirm-delete-profile').on('click', function (){
    let spinner = $(this)

    jQuery.ajax({
      type: "DELETE",
      data: JSON.stringify({parts: calendar_subscribe_object.parts}),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      url: calendar_subscribe_object.root + calendar_subscribe_object.parts.root + '/v1/' + calendar_subscribe_object.parts.type + '/delete_profile',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', calendar_subscribe_object.nonce )
      }
    }).done(function(){
      show_delete_profile_success(spinner)
    })
    .fail(function(e) {
      console.log(e)
      if ( e.status === 200 ){
        show_delete_profile_success(spinner)
        return
      }
      $('#confirm-delete-profile').toggleClass('loading')
      $('#delete-account-errors').empty().html(`<div class="grid-x"><div class="cell center">
        So sorry. Something went wrong. Please, contact us to help you through it, or just try again.<br>

        </div></div>`)
      $('#error').html(e)
      spinner.removeClass('active')
    })
  })

  function show_delete_profile_success(spinner){
    let wrapper = jQuery('#wrapper')
    wrapper.empty().html(`
          <div class="center">
          <h1>Your profile has been deleted!</h1>
          <p>Thank you for praying with us.<p>
          </div>
      `)
    spinner.removeClass('active')
    $(`#delete-profile-modal`).foundation('close')
  }

  /**
   * Display mobile commitments if screen dimension is narrow
   */
  if ( innerWidth < 475 ) {
    $( '.prayer-commitment' ).attr( 'class', 'prayer-commitment-tiny' );
    $( '.mc-title' ).show();
    $( '#mobile-commitments-container' ).show();
  }
})
