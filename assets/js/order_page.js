// The function toggles more (hidden) text when the user clicks on "Read more". The IF ELSE statement ensures that the text 'read more' and 'read less' changes interchangeably when clicked on.
jQuery(document).ready(function(){
jQuery('.moreless-button').click(function() {
  jQuery('.moretext').slideToggle();
  if (jQuery('.moreless-button').text() == "Read more") {
    jQuery(this).text("Read less")
  } else {
    jQuery(this).text("Read more")
  }
});
});