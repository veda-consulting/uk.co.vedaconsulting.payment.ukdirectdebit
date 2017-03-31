<!-- MV:custom Js, append old version custom changes into civi46 -->
{literal}
    <style type="text/css">
        #multiple_block > input {
            border: 1px solid;
            min-width: inherit !important;
            width: 43px;
        }
    </style>
    <script type="text/javascript">
        if(cj('tr').attr('id') !== "multiple_block") {

            cj("#bank_identification_number").parent().prepend('<div id ="multiple_block"></div>');
            cj("#multiple_block")

                .html('<input type = "text" size = "3" maxlength = "2" name = "block_1" id = "block_1"/>'
                    +' - <input type = "text" size = "3" maxlength = "2" name = "block_2" id ="block_2"/>'
                    +' - <input type = "text" size = "3" maxlength = "2" name = "block_3" id = "block_3"/>');

            cj('#block_1').change(function() {
                cj.fn.myFunction();
            });

            cj('#block_2').change(function() {
                cj.fn.myFunction();
            });

            cj('#block_3').change(function() {
                cj.fn.myFunction();
            });

            //function to get value of new title boxes and concatenate the values and display in mailing_title
            cj.fn.myFunction = function() {
                var field1 = cj("input#block_1").val();
                var field2 = cj("input#block_2").val();
                var field3 = cj("input#block_3").val();
                var finalFieldValue = field1 + field2 + field3;

                cj('input#bank_identification_number').val(finalFieldValue);
            };

            //hide the mailing title
            cj("#bank_identification_number").hide();

            //split the value of mailing_title
            //make it to appear on the new three title boxes
            var fieldValue = cj("#bank_identification_number").val();

            var fieldLength;
            if ( fieldValue !== undefined ) {
                fieldLength = fieldValue.length;
            } else {
                fieldLength = 0;
            }

            if (fieldLength !== 0) {

                var fieldSplit = (fieldValue+'').split('');

                cj('#block_1').val(fieldSplit[0]+fieldSplit[1]);

                if(!(fieldSplit[0]+fieldSplit[1])) {
                    cj('#block_1').val("");
                }

                cj('#block_2').val(fieldSplit[2]+fieldSplit[3]);

                if(!(fieldSplit[2]+fieldSplit[3])) {
                    cj('#block_2').val("");
                }

                cj('#block_3').val(fieldSplit[4]+fieldSplit[5]);

                if(!(fieldSplit[4]+fieldSplit[5])) {
                    cj('#block_3').val("");
                }

            }
        }

    </script>
{/literal}