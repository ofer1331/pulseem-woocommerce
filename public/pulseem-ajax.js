/**
 * Checkout AJAX Handler Script
 *
 * Frontend JavaScript for managing checkout form interactions:
 * - Real-time cart abandonment tracking
 * - Form field change monitoring
 * - Email validation and data collection
 * - Agreement checkbox handling
 * - AJAX communication with server
 * Provides seamless user data capture during checkout process.
 *
 * @since      1.0.0
 * @version    1.3.7
 */ jQuery(document).ready(function ($) {
  // Store previous values to prevent unnecessary server calls
  let previousValues = {
    billing_email: "",
    billing_phone: "",
    first_name: "",
    last_name: "",
    checkout_agreement: -1, // Default value for the agreement checkbox
  };

  // Function to send data to the server via AJAX
  function ajaxCartAbandoned(email, phone, first_name, last_name, user_agree) {
    $.ajax({
      type: "post",
      url: pulseem_ajax_obj.ajax_url,
      data: {
        action: "pulseem_change_checkout_data",
        nonce: pulseem_ajax_obj.nonce,
        email: email,
        phone: phone,
        first_name: first_name,
        last_name: last_name,
        user_agree: user_agree,
      },
      dataType: "json",
      success: function (response) {
        // Data sent successfully
      },
      error: function (xhr, status, error) {
        // Request failed silently
      },
    });
  }

  // Function to gather the current values from all relevant fields
  function getCurrentValues() {
    return {
      billing_email: $(
        "form.woocommerce-checkout input[name=billing_email]"
      ).val(),
      billing_phone: $(
        "form.woocommerce-checkout input[name=billing_phone]"
      ).val(),
      first_name: $(
        "form.woocommerce-checkout input[name=billing_first_name]"
      ).val(),
      last_name: $(
        "form.woocommerce-checkout input[name=billing_last_name]"
      ).val(),
      checkout_agreement: $("#pulseem_checkout_agreement").prop("checked")
        ? 1
        : 0,
    };
  }

  // Function to check if any value has changed and send an AJAX request if necessary
  function checkAndSend() {
    const currentValues = getCurrentValues();

    // Compare current values with previous values
    if (
      currentValues.billing_email !== previousValues.billing_email ||
      currentValues.billing_phone !== previousValues.billing_phone ||
      currentValues.first_name !== previousValues.first_name ||
      currentValues.last_name !== previousValues.last_name ||
      currentValues.checkout_agreement !== previousValues.checkout_agreement
    ) {
      // Update previous values to current values
      previousValues = { ...currentValues };

      // Trigger the AJAX call
      ajaxCartAbandoned(
        currentValues.billing_email,
        currentValues.billing_phone,
        currentValues.first_name,
        currentValues.last_name,
        currentValues.checkout_agreement
      );
    }
  }

  // Attach event listeners to all input fields and the agreement checkbox
  $(document).on(
    "input change",
    "form.woocommerce-checkout :input",
    function () {
      checkAndSend();
    }
  );

  // Initial trigger for logged-in users or preloaded values
  if ($(".logged-in.woocommerce-checkout").length >= 1) {
    checkAndSend();
  }
});
