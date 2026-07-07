(function () {
  function initNewsDateFilter(form) {
    var yearSelect = form.querySelector("[data-news-date-year]");
    var monthSelect = form.querySelector("[data-news-date-month]");
    var daySelect = form.querySelector("[data-news-date-day]");
    if (!yearSelect || !monthSelect || !daySelect) {
      return;
    }

    var monthLengths = {};
    try {
      monthLengths = JSON.parse(form.getAttribute("data-month-lengths") || "{}");
    } catch (error) {
      monthLengths = {};
    }

    function selectedDayValue() {
      return parseInt(daySelect.value, 10) || 0;
    }

    function dayAllLabel() {
      return form.getAttribute("data-ak-date-filter-compact") === "1" ? "روز" : "همه روزها";
    }

    function rebuildDayOptions() {
      var year = parseInt(yearSelect.value, 10) || 0;
      var month = parseInt(monthSelect.value, 10) || 0;
      var previousDay = selectedDayValue();

      daySelect.innerHTML = "";

      if (year <= 0 || month <= 0) {
        daySelect.disabled = true;
        var disabledOption = document.createElement("option");
        disabledOption.value = "0";
        disabledOption.textContent = dayAllLabel();
        disabledOption.selected = true;
        daySelect.appendChild(disabledOption);
        return;
      }

      var maxDay = 31;
      var yearKey = String(year);
      if (monthLengths[yearKey] && monthLengths[yearKey][month]) {
        maxDay = monthLengths[yearKey][month];
      }

      daySelect.disabled = false;

      var defaultOption = document.createElement("option");
      defaultOption.value = "0";
      defaultOption.textContent = dayAllLabel();
      defaultOption.selected = previousDay <= 0;
      daySelect.appendChild(defaultOption);

      for (var day = 1; day <= maxDay; day += 1) {
        var option = document.createElement("option");
        option.value = String(day);
        option.textContent = String(day);
        if (day === previousDay) {
          option.selected = true;
          defaultOption.selected = false;
        }
        daySelect.appendChild(option);
      }
    }

    yearSelect.addEventListener("change", rebuildDayOptions);
    monthSelect.addEventListener("change", rebuildDayOptions);
    rebuildDayOptions();
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-ak-news-date-filter]").forEach(initNewsDateFilter);
  });
})();
